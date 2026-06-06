<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Jobs\ExportAuditLogsJob;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Resources\AuditLogResource;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('forbids developers from viewing organization audit logs', function (): void {
    [$organization, $developer] = auditLogApiFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs")
        ->assertForbidden();
});

it('shows before and after state to owners only', function (): void {
    [$organization, $owner] = auditLogApiFixture(TeamRole::OWNER);

    AuditLog::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'actor_id' => (string) $owner->getKey(),
        'operation' => 'team.role_changed',
        'resource_type' => null,
        'resource_id' => null,
        'before_state' => ['role' => 'developer'],
        'after_state' => ['role' => 'admin'],
        'created_at' => now(),
    ]);

    AuditLogResource::$includeSensitiveState = true;

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs")
        ->assertOk()
        ->assertJsonPath('data.0.beforeState.role', 'developer')
        ->assertJsonPath('data.0.afterState.role', 'admin');
});

it('hides before and after state from admins', function (): void {
    [$organization, , $admin] = auditLogApiFixture(TeamRole::OWNER, withAdmin: true);

    AuditLog::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'actor_id' => (string) $admin->getKey(),
        'operation' => 'team.role_changed',
        'resource_type' => null,
        'resource_id' => null,
        'before_state' => ['role' => 'developer'],
        'after_state' => ['role' => 'admin'],
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs")
        ->assertOk()
        ->assertJsonMissingPath('data.0.beforeState')
        ->assertJsonMissingPath('data.0.afterState');
});

it('allows owners to export audit logs and forbids admins', function (): void {
    [$organization, $owner, $admin] = auditLogApiFixture(TeamRole::OWNER, withAdmin: true);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs/export?date_from=2026-01-01&date_to=2026-01-31")
        ->assertOk();

    $this->actingAs($admin)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs/export?date_from=2026-01-01&date_to=2026-01-31")
        ->assertForbidden();
});

it('queues export when record count exceeds threshold', function (): void {
    Queue::fake();
    config(['audit.export.queue_threshold' => 1]);

    [$organization, $owner] = auditLogApiFixture(TeamRole::OWNER);

    foreach (range(1, 2) as $index) {
        AuditLog::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'actor_id' => (string) $owner->getKey(),
            'operation' => 'server.registered',
            'created_at' => now()->subDays($index),
        ]);
    }

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/audit-logs/export?date_from=".now()->subDays(90)->toDateString().'&date_to='.now()->toDateString())
        ->assertAccepted()
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(ExportAuditLogsJob::class);
});

/**
 * @return array{0: Organization, 1: User, 2?: User}
 */
function auditLogApiFixture(TeamRole $primaryRole, bool $withAdmin = false): array
{
    $organization = Organization::query()->create([
        'name' => 'Audit API Org',
        'slug' => 'audit-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $primary = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($primary->getKey(), ['role' => $primaryRole->value]);

    if (! $withAdmin) {
        return [$organization, $primary];
    }

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($admin->getKey(), ['role' => TeamRole::ADMIN->value]);

    return [$organization, $primary, $admin];
}
