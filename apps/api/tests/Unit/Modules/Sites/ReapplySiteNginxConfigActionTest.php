<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\ReapplySiteNginxConfigAction;
use App\Modules\Sites\Actions\UpdateNginxConfigAction;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('generates nginx config and delegates apply', function (): void {
    [$site, $owner] = reapplySiteNginxFixture();

    $generator = \Mockery::mock(NginxConfigGeneratorInterface::class);
    $generator->shouldReceive('generate')
        ->once()
        ->with(\Mockery::on(fn (Site $s): bool => (string) $s->getKey() === (string) $site->getKey()))
        ->andReturn('server { fastcgi_pass unix:/var/run/php/php8.4-fpm.sock; }');

    $update = \Mockery::mock(UpdateNginxConfigAction::class);
    $update->shouldReceive('execute')
        ->once()
        ->withArgs(function (Site $s, User $actor, string $config) use ($site, $owner): bool {
            return (string) $s->getKey() === (string) $site->getKey()
                && (string) $actor->getKey() === (string) $owner->getKey()
                && str_contains($config, 'php8.4-fpm.sock');
        })
        ->andReturn($site);

    $result = (new ReapplySiteNginxConfigAction($generator, $update))->execute($site, $owner);

    expect((string) $result->getKey())->toBe((string) $site->getKey());
});

/**
 * @return array{0: Site, 1: User}
 */
function reapplySiteNginxFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Reapply Nginx Org',
        'slug' => 'reapply-nginx-'.Str::random(6),
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
        'hostname' => 'reapply-nginx.test',
        'ip_address' => '10.0.0.85',
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
        'domain' => 'reapply-nginx.example.test',
        'aliases' => [],
        'webroot' => '/var/www/reapply-nginx.example.test/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'php_version' => '8.4',
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
        'auto_deploy_enabled' => false,
    ]);

    return [$site, $owner];
}
