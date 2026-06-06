<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Models\DigitalOceanDnsConnection;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createDigitalOceanIntegrationFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'DigitalOcean Org',
        'slug' => 'digitalocean-org-'.Str::random(6),
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

it('connects digitalocean stores token and lists domains without exposing secrets', function (): void {
    [$organization, $owner] = createDigitalOceanIntegrationFixture();

    Http::fake([
        'api.digitalocean.com/v2/account' => Http::response(['account' => ['uuid' => 'acc-1']]),
        'api.digitalocean.com/v2/domains*' => Http::response([
            'domains' => [
                ['name' => 'example.test'],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean/connect", [
            'token' => 'do_test_secret_token_value',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'connected')
        ->assertJsonMissing(['token'])
        ->assertJsonMissing(['do_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'digitalocean_dns.connected')->exists())->toBeTrue();
    expect(DigitalOceanDnsConnection::query()->where('organization_id', $organization->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean")
        ->assertOk()
        ->assertJsonPath('data.connected', true);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean/zones")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'example.test')
        ->assertJsonMissing(['do_test_secret_token_value']);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean/disconnect")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'digitalocean_dns.disconnected')->exists())->toBeTrue();
});

it('assigns a digitalocean domain to a project for org admins', function (): void {
    [$organization, $owner] = createDigitalOceanIntegrationFixture();

    Http::fake([
        'api.digitalocean.com/v2/account' => Http::response(['account' => ['uuid' => 'acc-1']]),
        'api.digitalocean.com/v2/domains*' => Http::response([
            'domains' => [
                ['name' => 'do-example.test'],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean/connect", [
            'token' => 'do_test_secret_token_value',
        ])
        ->assertCreated();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'DO Project',
        'description' => null,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/projects/{$project->id}/dns-zones", [
            'dnsProvider' => 'digitalocean',
            'zoneId' => 'do-example.test',
            'baseDomain' => 'do-example.test',
        ])
        ->assertCreated()
        ->assertJsonPath('data.dnsProvider', 'digitalocean')
        ->assertJsonPath('data.baseDomain', 'do-example.test');

    $projectDnsZone = ProjectDnsZone::query()->where('project_id', $project->id)->firstOrFail();

    expect($projectDnsZone->dns_provider->value)->toBe('digitalocean');
});

it('forbids developers from connecting digitalocean', function (): void {
    [$organization, $developer] = createDigitalOceanIntegrationFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/integrations/digitalocean/connect", [
            'token' => 'do_test_secret_token_value',
        ])
        ->assertForbidden();
});
