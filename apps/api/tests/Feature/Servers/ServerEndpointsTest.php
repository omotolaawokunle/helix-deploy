<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

it('forbids cross organization server access', function (): void {
    $firstOrg = Organization::query()->create([
        'name' => 'First Servers Org',
        'slug' => 'first-servers-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $firstOrg->generateAndStoreMasterKey();

    $secondOrg = Organization::query()->create([
        'name' => 'Second Servers Org',
        'slug' => 'second-servers-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $secondOrg->generateAndStoreMasterKey();

    $firstOwner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $firstOrg->getKey(),
    ]);
    $secondOwner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $secondOrg->getKey(),
    ]);

    $firstOrg->users()->attach($firstOwner->getKey(), ['role' => TeamRole::OWNER->value]);
    $secondOrg->users()->attach($secondOwner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'hostname' => 'private.second-org.test',
        'ip_address' => '10.12.0.44',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $secondOwner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/servers/{$server->id}")
        ->assertForbidden();
});

it('organization servers index supports pagination filter search and sort', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Listing Org',
        'slug' => 'listing-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    foreach (range(1, 18) as $index) {
        Server::query()->withoutGlobalScope('owned_by_organization')->create([
            'organization_id' => (string) $organization->getKey(),
            'hostname' => "node-{$index}.listing.test",
            'ip_address' => "10.20.0.{$index}",
            'ssh_port' => 22,
            'ssh_user' => 'deploy',
            'provider' => $index % 2 === 0 ? 'aws' : 'generic',
            'status' => $index % 2 === 0 ? 'active' : 'connecting',
            'management_mode' => 'managed',
            'created_by' => (string) $owner->getKey(),
            'tags' => [],
            'installed_services' => [],
        ]);
    }

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/servers")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(15, 'data');

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/servers?filter[status]=active&search=node-18&sort=servers.hostname")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hostname', 'node-18.listing.test');
});
