<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\EnvVarsPullPreviewReady;
use App\Modules\Sites\Jobs\FetchEnvVarsPullPreviewJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('fetch env vars pull preview job caches diff without secret values', function (): void {
    Event::fake([EnvVarsPullPreviewReady::class]);

    [$site, $organization] = envPullPreviewFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeSecret($organization, $site, 'APP_KEY', 'helix-value');

    $remotePath = '/var/www/'.$site->domain.'/shared/.env';

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addResponse('test -f *', new SSHResult('test -f', 0, '', '', 0.0))
        ->addResponse('cat *', new SSHResult('cat', 0, "APP_KEY=server-value\nNEW_KEY=abc\n", '', 0.0));

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $this->app->instance(SSHManager::class, $manager);

    $job = new FetchEnvVarsPullPreviewJob((string) $site->getKey());
    $job->handle(app(\App\Modules\Sites\Actions\EnvVarActions\FetchEnvVarsPullPreviewAction::class));

    $cached = Cache::get(FetchEnvVarsPullPreviewJob::cacheKey((string) $site->getKey()));

    expect($cached['status'])->toBe('ready')
        ->and($cached['diff']['serverFileExists'])->toBeTrue()
        ->and($cached['diff']['new'])->toBe(['NEW_KEY'])
        ->and($cached['diff']['changed'])->toBe(['APP_KEY'])
        ->and($cached['diff']['helixOnly'])->toBe([]);

    expect(json_encode($cached))->not->toContain('server-value')
        ->and(json_encode($cached))->not->toContain('helix-value');

    Event::assertDispatched(EnvVarsPullPreviewReady::class);
});

it('fetch env vars pull preview job reports missing server file', function (): void {
    Event::fake([EnvVarsPullPreviewReady::class]);

    [$site] = envPullPreviewFixture();

    $fake = (new FakeSSHConnection())
        ->connect()
        ->addSequence('test -f *',
            new SSHResult('test -f', 1, '', '', 0.0),
            new SSHResult('test -f', 1, '', '', 0.0),
            new SSHResult('test -f', 1, '', '', 0.0),
        );

    $manager = \Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);
    $this->app->instance(SSHManager::class, $manager);

    $job = new FetchEnvVarsPullPreviewJob((string) $site->getKey());
    $job->handle(app(\App\Modules\Sites\Actions\EnvVarActions\FetchEnvVarsPullPreviewAction::class));

    $cached = Cache::get(FetchEnvVarsPullPreviewJob::cacheKey((string) $site->getKey()));

    expect($cached['diff']['serverFileExists'])->toBeFalse()
        ->and($cached['diff']['new'])->toBe([]);
});

/**
 * @return array{0: Site, 1: Organization}
 */
function envPullPreviewFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Env Pull Preview Org',
        'slug' => 'env-pull-preview-'.Str::random(6),
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
        'hostname' => 'env-pull.test',
        'ip_address' => '10.0.0.71',
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
        'domain' => 'pull-env.example.test',
        'aliases' => [],
        'webroot' => '/var/www/pull-env.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    return [$site, $organization];
}
