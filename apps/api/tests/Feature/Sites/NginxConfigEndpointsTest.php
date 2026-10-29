<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Jobs\FetchSiteNginxConfigJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns loading and reads the installed nginx file instead of generating one', function (): void {
    Queue::fake();

    [$owner, $site] = nginxConfigSite('nginx-read');

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/nginx-config?refresh=1")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.config', '');

    Queue::assertPushed(FetchSiteNginxConfigJob::class, function (FetchSiteNginxConfigJob $job) use ($site): bool {
        return $job->siteId === (string) $site->getKey();
    });

    expect(AuditLog::query()->where('operation', 'site.nginx_config.viewed')->exists())->toBeTrue();
});

it('returns the cached server nginx file without reading again', function (): void {
    Queue::fake();

    [$owner, $site] = nginxConfigSite('nginx-cached');

    $installed = "location /app {\n    proxy_pass http://127.0.0.1:8080;\n}\n";

    Cache::put(
        FetchSiteNginxConfigJob::cacheKey((string) $site->getKey()),
        [
            'status' => 'ready',
            'config' => $installed,
        ],
        now()->addMinutes(2),
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/nginx-config")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.config', $installed);

    Queue::assertNothingPushed();
});

it('refreshes a cached nginx file by reading the server again', function (): void {
    Queue::fake();

    [$owner, $site] = nginxConfigSite('nginx-refresh');

    Cache::put(
        FetchSiteNginxConfigJob::cacheKey((string) $site->getKey()),
        [
            'status' => 'ready',
            'config' => "server { listen 80; }\n",
        ],
        now()->addMinutes(2),
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/nginx-config?refresh=1")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.config', '');

    Queue::assertPushed(FetchSiteNginxConfigJob::class);
});

it('forbids cross organization nginx config access', function (): void {
    [$owner, $site] = nginxConfigSite('nginx-cross-a');
    [$otherOwner] = nginxConfigSite('nginx-cross-b');

    $this->actingAs($otherOwner)
        ->getJson("/api/v1/sites/{$site->id}/nginx-config")
        ->assertForbidden();
});

/**
 * @return array{0: User, 1: Site}
 */
function nginxConfigSite(string $slug): array
{
    $organization = Organization::query()->create([
        'name' => 'Nginx Config '.$slug,
        'slug' => $slug.'-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => $slug.'.example.test',
        'ip_address' => '10.'.random_int(1, 200).'.'.random_int(0, 250).'.'.random_int(1, 250),
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => $slug.'.example.test',
        'webroot' => '/var/www/'.$slug.'.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);

    return [$owner, $site];
}
