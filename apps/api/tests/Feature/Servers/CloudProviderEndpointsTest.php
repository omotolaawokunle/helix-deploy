<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createCloudProviderFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Cloud Provider Org',
        'slug' => 'cloud-provider-'.Str::random(6),
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

it('stores cloud provider credentials lists instances and never returns secret values', function (): void {
    [$organization, $owner] = createCloudProviderFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/cloud-providers", [
            'provider' => 'hetzner',
            'token' => 'hzn_test_secret_token_value',
        ])
        ->assertCreated()
        ->assertJsonPath('data.provider', 'hetzner')
        ->assertJsonMissing(['token'])
        ->assertJsonMissing(['hzn_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'credential.created')->exists())->toBeTrue();

    Http::fake([
        'api.hetzner.cloud/v1/servers*' => Http::response([
            'servers' => [
                [
                    'id' => 12345,
                    'name' => 'web-01',
                    'status' => 'running',
                    'public_net' => [
                        'ipv4' => ['ip' => '203.0.113.10'],
                    ],
                    'server_type' => ['name' => 'cx22'],
                    'datacenter' => [
                        'location' => ['name' => 'fsn1'],
                    ],
                    'image' => [
                        'os_flavor' => 'ubuntu',
                        'os_version' => '22.04',
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/cloud-providers")
        ->assertOk()
        ->assertJsonFragment(['provider' => 'hetzner', 'configured' => true]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/cloud-providers/hetzner/instances")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'web-01')
        ->assertJsonPath('data.0.ipAddress', '203.0.113.10')
        ->assertJsonPath('data.0.region', 'fsn1')
        ->assertJsonMissing(['hzn_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'credential.accessed')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/cloud-providers/hetzner")
        ->assertNoContent();
});

it('lists digitalocean droplets when credentials are configured', function (): void {
    [$organization, $owner] = createCloudProviderFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/cloud-providers", [
            'provider' => 'digitalocean',
            'token' => 'dop_v1_test_secret',
        ])
        ->assertCreated();

    Http::fake([
        'api.digitalocean.com/v2/droplets*' => Http::response([
            'droplets' => [
                [
                    'id' => 98765,
                    'name' => 'api-droplet',
                    'status' => 'active',
                    'size_slug' => 's-1vcpu-1gb',
                    'region' => ['slug' => 'nyc3'],
                    'networks' => [
                        'v4' => [
                            ['type' => 'public', 'ip_address' => '198.51.100.5'],
                        ],
                    ],
                    'image' => [
                        'distribution' => 'Ubuntu',
                        'name' => '22.04 x64',
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/cloud-providers/digitalocean/instances")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'api-droplet')
        ->assertJsonPath('data.0.ipAddress', '198.51.100.5')
        ->assertJsonMissing(['dop_v1_test_secret']);
});

it('forbids developers from storing cloud provider credentials', function (): void {
    [$organization, $developer] = createCloudProviderFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/cloud-providers", [
            'provider' => 'hetzner',
            'token' => 'hzn_test_secret_token_value',
        ])
        ->assertForbidden();
});

it('persists provider metadata when registering a server from cloud import', function (): void {
    Queue::fake();

    [$organization, $owner] = createCloudProviderFixture();

    Http::fake();

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/servers", [
            'name' => 'web-01',
            'hostname' => 'web-01',
            'ipAddress' => '203.0.113.10',
            'sshPort' => 22,
            'sshUser' => 'deploy',
            'provider' => 'hetzner',
            'region' => 'fsn1',
            'serverType' => 'cx22',
            'providerInstanceId' => '12345',
            'os' => 'ubuntu 22.04',
            'managementMode' => 'managed',
            'authMethod' => 'generate',
            'tags' => ['production'],
        ]);

    $response->assertOk();

    $serverId = $response->json('data.server.id');
    expect($serverId)->not->toBeNull();

    $server = Server::query()
        ->withoutGlobalScope('owned_by_organization')
        ->findOrFail($serverId);

    expect($server->provider_instance_id)->toBe('12345');
    expect($server->region)->toBe('fsn1');
    expect($server->server_type)->toBe('cx22');
    expect($server->os)->toBe('ubuntu 22.04');

    $response
        ->assertJsonPath('data.server.providerInstanceId', '12345')
        ->assertJsonPath('data.server.region', 'fsn1')
        ->assertJsonPath('data.server.serverType', 'cx22')
        ->assertJsonPath('data.server.os', 'ubuntu 22.04');
});
