<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Models\ServerGroup;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User, Server}
 */
function createServerGroupApiFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Server Groups Org',
        'slug' => 'server-groups-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'group-host.'.Str::random(6).'.test',
        'ip_address' => '10.30.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $user->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return [$organization, $user, $server];
}

it('creates updates syncs and deletes server groups with audit logs', function (): void {
    [$organization, $owner, $server] = createServerGroupApiFixture();

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/server-groups", [
            'name' => 'Production Pool',
            'description' => 'Primary hosts',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Production Pool');

    $groupId = (string) $createResponse->json('data.id');

    expect(AuditLog::query()->where('operation', 'server_group.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->putJson("/api/v1/server-groups/{$groupId}/servers", [
            'serverIds' => [(string) $server->getKey()],
        ])
        ->assertOk();

    expect(AuditLog::query()->where('operation', 'server_group.servers_synced')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/servers", [
            'filter' => ['server_group_id' => $groupId],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($owner)
        ->patchJson("/api/v1/server-groups/{$groupId}", [
            'name' => 'Production Fleet',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Production Fleet');

    $this->actingAs($owner)
        ->deleteJson("/api/v1/server-groups/{$groupId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'server_group.deleted')->exists())->toBeTrue();
});

it('forbids cross organization server group access', function (): void {
    [$firstOrg, $firstOwner] = createServerGroupApiFixture();
    [$secondOrg] = createServerGroupApiFixture();

    $group = ServerGroup::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'Private Group',
        'description' => null,
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/server-groups/{$group->id}")
        ->assertForbidden();
});

it('forbids developers from creating server groups', function (): void {
    [$organization, $developer] = createServerGroupApiFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/server-groups", [
            'name' => 'Dev Group',
        ])
        ->assertForbidden();
});
