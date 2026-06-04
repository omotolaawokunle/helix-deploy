<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('includes x-request-id on api responses', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Request ID Org',
        'slug' => 'request-id-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $response = $this->actingAs($owner)
        ->getJson('/api/v1/organizations');

    $requestId = $response->headers->get('X-Request-ID');

    expect($requestId)->not->toBeNull()
        ->and(Str::isUuid((string) $requestId))->toBeTrue();
});

it('stores request id from middleware on audit log records', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Audit Request ID Org',
        'slug' => 'audit-request-id-'.Str::random(6),
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
        'hostname' => 'audit-request-id.test',
        'ip_address' => '10.0.0.77',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/test-connection")
        ->assertAccepted();

    $requestId = (string) $response->headers->get('X-Request-ID');

    $audit = AuditLog::query()
        ->where('operation', 'server.connection_test_requested')
        ->latest('created_at')
        ->first();

    expect($audit?->request_id)->toBe($requestId);
});
