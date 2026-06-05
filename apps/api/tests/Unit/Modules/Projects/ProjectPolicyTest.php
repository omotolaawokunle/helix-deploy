<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Policies\ProjectPolicy;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('enforces project role permissions', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Policy Org',
        'slug' => 'policy-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($viewer->getKey(), ['role' => TeamRole::VIEWER->value]);

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Project',
        'description' => null,
    ]);

    $policy = new ProjectPolicy();

    expect($policy->view($viewer, $project))->toBeTrue()
        ->and($policy->create($viewer, $organization))->toBeFalse()
        ->and($policy->update($owner, $project))->toBeTrue()
        ->and($policy->delete($owner, $project))->toBeTrue();
});
