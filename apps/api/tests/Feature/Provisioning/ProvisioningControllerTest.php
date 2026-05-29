<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Modules\Provisioning\Jobs\ProvisionServerJob;
use App\Modules\Servers\Models\Server;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('dispatches provisioning job and returns channel metadata', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Provisioning Controller Org',
        'slug' => 'provisioning-controller-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'provisioning-api.test',
        'ip_address' => '10.0.0.99',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'credential_id' => null,
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/provision", [
            'scripts' => ['supervisor'],
            'options' => [],
        ])
        ->assertAccepted()
        ->assertJsonPath('channel', "server.{$server->id}.provisioning")
        ->assertJsonStructure(['jobId', 'channel']);

    Queue::assertPushed(ProvisionServerJob::class, 1);
});
