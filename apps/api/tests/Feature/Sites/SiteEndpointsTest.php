<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Events\SiteProvisioningStarted;
use App\Modules\Sites\Jobs\CreateSiteJob;
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
