<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Policies\TeamPolicy;
use Illuminate\Support\Str;

it('enforces team policy permissions by organization role and membership', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Team Policy Org',
        'slug' => 'team-policy-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $developer = User::factory()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $outsider = User::factory()->create();

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Team',
        'slug' => 'policy-team',
    ]);

    $policy = new TeamPolicy();

    expect($policy->viewAny($developer, $organization))->toBeTrue()
        ->and($policy->view($developer, $team))->toBeFalse()
        ->and($policy->create($developer, $organization))->toBeFalse()
        ->and($policy->view($owner, $team))->toBeTrue()
        ->and($policy->manageMembers($owner, $team))->toBeTrue()
        ->and($policy->view($outsider, $team))->toBeFalse();

    $team->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    expect($policy->view($developer, $team))->toBeTrue()
        ->and($policy->manageMembers($developer, $team))->toBeFalse();
});
