<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('claims a discovered site via api', function (): void {
    [$site, $owner] = claimSiteEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/claim", [
            'repositoryUrl' => 'https://github.com/helix/example.git',
            'repositoryProvider' => 'github',
            'deployBranch' => 'main',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', SiteStatus::ACTIVE->value)
        ->assertJsonPath('data.repositoryUrl', 'https://github.com/helix/example.git')
        ->assertJsonPath('data.repositoryProvider', 'github')
        ->assertJsonPath('data.deployBranch', 'main');

    expect(AuditLog::query()->where('operation', 'site.claimed')->exists())->toBeTrue();
});

it('returns webhook secret when auto deploy is enabled during claim', function (): void {
    [$site, $owner] = claimSiteEndpointFixture();

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/claim", [
            'repositoryUrl' => 'https://github.com/helix/example.git',
            'repositoryProvider' => 'github',
            'deployBranch' => 'main',
            'autoDeployEnabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', SiteStatus::ACTIVE->value)
        ->assertJsonPath('data.autoDeployEnabled', true)
        ->assertJsonStructure(['webhookSecret', 'data' => ['webhookUrl', 'hasWebhookSecret']]);

    expect($response->json('webhookSecret'))->not->toBeEmpty();
});

it('rejects claim for active sites', function (): void {
    [$site, $owner] = claimSiteEndpointFixture(status: SiteStatus::ACTIVE);

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/claim", [
            'repositoryUrl' => 'https://github.com/helix/example.git',
            'repositoryProvider' => 'github',
            'deployBranch' => 'main',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Site is not in discovered status.');
});

it('validates required claim fields', function (): void {
    [$site, $owner] = claimSiteEndpointFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/claim", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repositoryUrl', 'repositoryProvider', 'deployBranch']);
});

it('forbids claim for developer role', function (): void {
    [$site, $owner, $developer] = claimSiteEndpointFixture(withDeveloper: true);

    $this->actingAs($developer)
        ->postJson("/api/v1/sites/{$site->id}/claim", [
            'repositoryUrl' => 'https://github.com/helix/example.git',
            'repositoryProvider' => 'github',
            'deployBranch' => 'main',
        ])
        ->assertForbidden();
});

it('forbids cross organization claim', function (): void {
    [$site] = claimSiteEndpointFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Claim Org',
        'slug' => 'other-claim-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrg->generateAndStoreMasterKey();

    $intruder = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $otherOrg->getKey(),
    ]);
    $otherOrg->users()->attach($intruder->getKey(), ['role' => TeamRole::OWNER->value]);

    $this->actingAs($intruder)
        ->postJson("/api/v1/sites/{$site->id}/claim", [
            'repositoryUrl' => 'https://github.com/helix/example.git',
            'repositoryProvider' => 'github',
            'deployBranch' => 'main',
        ])
        ->assertForbidden();
});

/**
 * @return array{0: Site, 1: User, 2?: User}
 */
function claimSiteEndpointFixture(
    SiteStatus $status = SiteStatus::DISCOVERED,
    bool $withDeveloper = false,
): array {
    $organization = Organization::query()->create([
        'name' => 'Claim Site Endpoint Org',
        'slug' => 'claim-site-endpoint-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $developer = null;
    if ($withDeveloper) {
        $developer = User::factory()->create([
            'email_verified_at' => now(),
            'current_organization_id' => (string) $organization->getKey(),
        ]);
        $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    }

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'claim-site-endpoint.test',
        'ip_address' => '10.0.0.32',
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
        'domain' => 'discovered-endpoint.example.test',
        'aliases' => [],
        'webroot' => '/var/www/discovered-endpoint.example.test/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => $status,
    ]);

    if ($withDeveloper && $developer !== null) {
        return [$site, $owner, $developer];
    }

    return [$site, $owner];
}
