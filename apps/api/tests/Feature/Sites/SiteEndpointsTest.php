<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Events\SiteProvisioningStarted;
use App\Modules\Sites\Jobs\CreateSiteJob;
use App\Modules\Sites\Jobs\IssueSiteSslJob;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\Event;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('dispatches site provisioning job and returns accepted response', function (): void {
    Queue::fake();
    Event::fake([SiteProvisioningStarted::class]);

    $organization = Organization::query()->create([
        'name' => 'Sites API Org',
        'slug' => 'sites-api-org-'.Str::random(6),
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
        'hostname' => 'sites-api.example.test',
        'ip_address' => '10.0.0.50',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/sites", [
            'domain' => 'new-site.example.test',
            'runtime' => 'php',
            'phpVersion' => '8.3',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.status', SiteStatus::PROVISIONING->value)
        ->assertJsonPath('data.domain', 'new-site.example.test')
        ->assertJsonPath('channel', "server.{$server->id}.sites");

    Queue::assertPushedOn('provisioning', CreateSiteJob::class);
    Event::assertDispatched(SiteProvisioningStarted::class);
});

it('dispatches ssl retry job for failed ssl sites', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Sites SSL Org',
        'slug' => 'sites-ssl-org-'.Str::random(6),
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
        'hostname' => 'sites-ssl.example.test',
        'ip_address' => '10.0.0.51',
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
        'domain' => 'ssl-retry.example.test',
        'aliases' => [],
        'webroot' => '/var/www/ssl-retry.example.test/current/public',
        'runtime' => 'php',
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => SiteStatus::ACTIVE->value,
        'enable_ssl' => true,
        'ssl_status' => SslStatus::FAILED->value,
        'ssl_error' => 'challenge failed',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/ssl/retry")
        ->assertAccepted()
        ->assertJsonPath('data.sslStatus', SslStatus::PENDING->value)
        ->assertJsonPath('data.sslError', null);

    Queue::assertPushedOn('provisioning', IssueSiteSslJob::class, function (IssueSiteSslJob $job) use ($site): bool {
        return $job->siteId === (string) $site->getKey();
    });
});

it('rejects ssl retry when ssl is not enabled', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Sites SSL Disabled Org',
        'slug' => 'sites-ssl-disabled-org-'.Str::random(6),
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
        'hostname' => 'sites-no-ssl.example.test',
        'ip_address' => '10.0.0.52',
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
        'domain' => 'no-ssl.example.test',
        'aliases' => [],
        'webroot' => '/var/www/no-ssl.example.test/current/public',
        'runtime' => 'php',
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => SiteStatus::ACTIVE->value,
        'enable_ssl' => false,
        'ssl_status' => SslStatus::NONE->value,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/ssl/retry")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'SSL is not enabled for this site.');

    Queue::assertNothingPushed();
});
