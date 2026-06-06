<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Policies\PipelinePolicy;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use Illuminate\Support\Str;

it('enforces pipeline policy permissions by organization role', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Pipeline Policy Org',
        'slug' => 'pipeline-policy-'.Str::random(6),
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

    $pipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);

    $policy = new PipelinePolicy();

    expect($policy->viewAny($developer, $organization))->toBeTrue()
        ->and($policy->view($developer, $pipeline))->toBeTrue()
        ->and($policy->create($developer, $organization))->toBeFalse()
        ->and($policy->update($developer, $pipeline))->toBeFalse()
        ->and($policy->delete($developer, $pipeline))->toBeFalse()
        ->and($policy->view($owner, $pipeline))->toBeTrue()
        ->and($policy->update($owner, $pipeline))->toBeTrue()
        ->and($policy->view($outsider, $pipeline))->toBeFalse();
});

it('hides project scoped pipelines from scoped team members', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Scoped Pipeline Org',
        'slug' => 'scoped-pipeline-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $developer = User::factory()->create([
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $visibleProject = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Visible',
        'description' => null,
    ]);
    $hiddenProject = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Hidden',
        'description' => null,
    ]);

    $team = Team::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Scoped Team',
        'slug' => 'scoped-team',
    ]);
    $team->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    $team->projects()->attach($visibleProject->getKey());

    $visiblePipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $visibleProject->getKey(),
        'name' => 'Visible Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);
    $hiddenPipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $hiddenProject->getKey(),
        'name' => 'Hidden Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);

    $policy = new PipelinePolicy();

    expect($policy->view($developer, $visiblePipeline))->toBeTrue()
        ->and($policy->view($developer, $hiddenPipeline))->toBeFalse();
});
