<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Models\CloudflareConnection;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createCloudflareIntegrationFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Cloudflare Org',
        'slug' => 'cloudflare-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    return [$organization, $user];
}

it('connects cloudflare stores token and lists zones without exposing secrets', function (): void {
    [$organization, $owner] = createCloudflareIntegrationFixture();

    Http::fake([
        'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response([
            'success' => true,
            'result' => ['status' => 'active'],
        ]),
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'zone-123',
                    'name' => 'example.test',
                    'status' => 'active',
                ],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare/connect", [
            'token' => 'cf_test_secret_token_value',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'connected')
        ->assertJsonMissing(['token'])
        ->assertJsonMissing(['cf_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'cloudflare.connected')->exists())->toBeTrue();
    expect(CloudflareConnection::query()->where('organization_id', $organization->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare")
        ->assertOk()
        ->assertJsonPath('data.connected', true);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare/zones")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'example.test')
        ->assertJsonMissing(['cf_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'credential.accessed')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare/disconnect")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'cloudflare.disconnected')->exists())->toBeTrue();
});

it('forbids developers from connecting cloudflare', function (): void {
    [$organization, $developer] = createCloudflareIntegrationFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare/connect", [
            'token' => 'cf_test_secret_token_value',
        ])
        ->assertForbidden();
});

it('assigns a dns zone to a project for org admins', function (): void {
    [$organization, $owner] = createCloudflareIntegrationFixture();

    Http::fake([
        'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => true, 'result' => []]),
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => [
                ['id' => 'zone-123', 'name' => 'example.test', 'status' => 'active'],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/cloudflare/connect", [
            'token' => 'cf_test_secret_token_value',
        ])
        ->assertCreated();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Main Project',
        'description' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/projects/{$project->id}/dns-zones", [
            'dnsProvider' => 'cloudflare',
            'zoneId' => 'zone-123',
            'baseDomain' => 'example.test',
        ])
        ->assertCreated()
        ->assertJsonPath('data.baseDomain', 'example.test');

    expect(AuditLog::query()->where('operation', 'project.dns_zone.assigned')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/projects/{$project->id}/dns-zones")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $projectDnsZone = ProjectDnsZone::query()->where('project_id', $project->id)->firstOrFail();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/projects/{$project->id}/dns-zones/{$projectDnsZone->id}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'project.dns_zone.unassigned')->exists())->toBeTrue();
});
