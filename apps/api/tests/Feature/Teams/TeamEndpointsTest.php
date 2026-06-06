<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User, User}
 */
function createTeamApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Teams API Org',
        'slug' => 'teams-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    return [$organization, $owner, $developer];
}

it('creates lists updates and deletes teams with audit logs', function (): void {
    [$organization, $owner] = createTeamApiFixture();

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/teams", [
            'name' => 'Platform Team',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Platform Team');

    $teamId = (string) $createResponse->json('data.id');

    expect(AuditLog::query()->where('operation', 'team.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/teams")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Platform Team');

    $this->actingAs($owner)
        ->patchJson("/api/v1/teams/{$teamId}", [
            'name' => 'Core Platform',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Core Platform');

    expect(AuditLog::query()->where('operation', 'team.updated')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/teams/{$teamId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'team.deleted')->exists())->toBeTrue();
});

it('manages team members and project scope', function (): void {
    [$organization, $owner, $developer] = createTeamApiFixture();

    $projectA = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Project A',
        'description' => null,
    ]);
    $projectB = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Project B',
        'description' => null,
    ]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Scoped Team',
        'slug' => 'scoped-team',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/teams/{$team->id}/members", [
            'userId' => (string) $developer->getKey(),
            'role' => TeamRole::DEVELOPER->value,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('team_user', [
        'team_id' => (string) $team->getKey(),
        'user_id' => (string) $developer->getKey(),
        'role' => TeamRole::DEVELOPER->value,
    ]);

    $this->actingAs($owner)
        ->putJson("/api/v1/teams/{$team->id}/projects", [
            'projectIds' => [(string) $projectA->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('data.projectIds', [(string) $projectA->getKey()]);

    expect(AuditLog::query()->where('operation', 'team.projects_synced')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->patchJson("/api/v1/teams/{$team->id}/members/{$developer->id}", [
            'role' => TeamRole::VIEWER->value,
        ])
        ->assertNoContent();

    $this->actingAs($developer)
        ->getJson("/api/v1/teams/{$team->id}/members")
        ->assertOk()
        ->assertJsonPath('data.0.role', TeamRole::VIEWER->value);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/teams/{$team->id}/members/{$developer->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('team_user', [
        'team_id' => (string) $team->getKey(),
        'user_id' => (string) $developer->getKey(),
    ]);

    expect($projectB)->not->toBeNull();
});

it('restricts team management to owners and admins', function (): void {
    [$organization, $owner, $developer] = createTeamApiFixture();

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/teams", [
            'name' => 'Blocked Team',
        ])
        ->assertForbidden();
});

it('denies cross organization team access', function (): void {
    [$organization, $owner] = createTeamApiFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Org',
        'slug' => 'other-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $otherOrg->generateAndStoreMasterKey();

    $intruder = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $otherOrg->getKey(),
    ]);
    $otherOrg->users()->attach($intruder->getKey(), ['role' => TeamRole::OWNER->value]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Private Team',
        'slug' => 'private-team',
    ]);

    $this->actingAs($intruder)
        ->getJson("/api/v1/teams/{$team->id}")
        ->assertForbidden();
});

it('limits team listing to member teams for non admin users', function (): void {
    [$organization, $owner, $developer] = createTeamApiFixture();

    $visibleTeam = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Visible Team',
        'slug' => 'visible-team',
    ]);
    Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Hidden Team',
        'slug' => 'hidden-team',
    ]);

    $visibleTeam->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->getJson("/api/v1/organizations/{$organization->id}/teams")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Visible Team');

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/teams")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
