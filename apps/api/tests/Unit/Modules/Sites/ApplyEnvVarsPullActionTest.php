<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\EnvVarActions\ApplyEnvVarsPullAction;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\EnvVarPullStrategy;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\EnvVarsPulled;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('apply env vars pull with add_new strategy creates missing keys only', function (): void {
    Event::fake([EnvVarsPulled::class]);

    [$site, $organization] = envPullApplyFixture();

    $fake = sshEnvFileFake("APP_KEY=server-value\nNEW_KEY=new-value\n");
    bindFakeSshManager($fake);

    $result = app(ApplyEnvVarsPullAction::class)->execute(
        $site,
        $organization,
        EnvVarPullStrategy::ADD_NEW,
    );

    expect($result)->toBe(['created' => 1, 'updated' => 0, 'deleted' => 0]);

    $helixValue = app(CredentialVaultInterface::class)->getSecret(
        (string) Credential::query()->where('name', 'APP_KEY')->value('id'),
        $organization,
    );
    expect($helixValue)->toBe('helix-value');
    sodium_memzero($helixValue);

    $newValue = app(CredentialVaultInterface::class)->getSecret(
        (string) Credential::query()->where('name', 'NEW_KEY')->value('id'),
        $organization,
    );
    expect($newValue)->toBe('new-value');
    sodium_memzero($newValue);

    Event::assertDispatched(EnvVarsPulled::class);
    expect(AuditLog::query()->where('operation', 'env_vars.pulled')->exists())->toBeTrue();
});

it('apply env vars pull with overwrite_changed strategy updates conflicts', function (): void {
    Event::fake([EnvVarsPulled::class]);

    [$site, $organization] = envPullApplyFixture();

    $fake = sshEnvFileFake("APP_KEY=server-value\nNEW_KEY=new-value\n");
    bindFakeSshManager($fake);

    $result = app(ApplyEnvVarsPullAction::class)->execute(
        $site,
        $organization,
        EnvVarPullStrategy::OVERWRITE_CHANGED,
    );

    expect($result)->toBe(['created' => 1, 'updated' => 1, 'deleted' => 0]);

    $helixValue = app(CredentialVaultInterface::class)->getSecret(
        (string) Credential::query()->where('name', 'APP_KEY')->value('id'),
        $organization,
    );
    expect($helixValue)->toBe('server-value');
    sodium_memzero($helixValue);
});

it('apply env vars pull with mirror_server strategy deletes helix-only keys', function (): void {
    Event::fake([EnvVarsPulled::class]);

    [$site, $organization] = envPullApplyFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeSecret($organization, $site, 'HELIX_ONLY', 'local');

    $fake = sshEnvFileFake("APP_KEY=server-value\n");
    bindFakeSshManager($fake);

    $result = app(ApplyEnvVarsPullAction::class)->execute(
        $site,
        $organization,
        EnvVarPullStrategy::MIRROR_SERVER,
    );

    expect($result)->toBe(['created' => 0, 'updated' => 1, 'deleted' => 1]);

    expect(Credential::query()->ofType(CredentialType::ENV_VAR)->where('name', 'HELIX_ONLY')->exists())->toBeFalse();
});

function sshEnvFileFake(string $content): FakeSSHConnection
{
    return (new FakeSSHConnection())
        ->connect()
        ->addResponse('test -f *', new SSHResult('test -f', 0, '', '', 0.0))
        ->addResponse('cat *', new SSHResult('cat', 0, $content, '', 0.0));
}

function bindFakeSshManager(FakeSSHConnection $fake): void
{
    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);
    app()->instance(SSHManager::class, $manager);
}

/**
 * @return array{0: Site, 1: Organization}
 */
function envPullApplyFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Env Pull Apply Org',
        'slug' => 'env-pull-apply-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-pull-apply.test',
        'ip_address' => '10.0.0.72',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'apply-env.example.test',
        'aliases' => [],
        'webroot' => '/var/www/apply-env.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    app(CredentialVaultInterface::class)->storeSecret($organization, $site, 'APP_KEY', 'helix-value');

    return [$site, $organization];
}
