<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Events\PipelineApproved;
use App\Modules\Pipelines\Events\PipelineRejected;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: \App\Modules\Organizations\Models\Organization, 1: User, 2: PipelineRun}
 */
function pipelineApprovalFixture(TeamRole $actorRole = TeamRole::ADMIN): array
{
    [$organization, , $site, $owner] = pipelineRunFixture();

    $actor = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($actor->getKey(), ['role' => $actorRole->value]);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::AWAITING_APPROVAL,
        'triggered_by' => (string) $owner->getKey(),
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $pipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Approval Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $owner->getKey(),
    ]);

    $run = PipelineRun::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'pipeline_id' => (string) $pipeline->getKey(),
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $deployment->getKey(),
        'triggered_by' => (string) $owner->getKey(),
        'status' => PipelineRunStatus::AWAITING_APPROVAL,
        'current_step_order' => 1,
        'metadata' => [],
    ]);

    $deployment->forceFill(['pipeline_run_id' => (string) $run->getKey()])->save();

    PipelineRunStep::query()->create([
        'pipeline_run_id' => (string) $run->getKey(),
        'name' => 'Production gate',
        'type' => PipelineStepType::APPROVE,
        'order' => 1,
        'status' => PipelineRunStepStatus::AWAITING_APPROVAL,
        'config' => [],
        'requires_approval' => true,
        'approver_role' => TeamRole::ADMIN,
        'retry_attempts' => 0,
    ]);

    return [$organization, $actor, $run];
}

it('approves a pipeline run and resumes execution', function (): void {
    Event::fake([PipelineApproved::class]);
    Queue::fake();

    [, $admin, $run] = pipelineApprovalFixture(TeamRole::ADMIN);

    $this->actingAs($admin)
        ->postJson("/api/v1/pipeline-runs/{$run->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', PipelineRunStatus::RUNNING->value);

    expect(AuditLog::query()->where('operation', 'pipeline_run.approved')->exists())->toBeTrue();
    Event::assertDispatched(PipelineApproved::class);
    Queue::assertPushed(\App\Modules\Pipelines\Jobs\RunPipelineJob::class);
});

it('rejects a pipeline run and marks deployment failed', function (): void {
    Event::fake([PipelineRejected::class]);

    [, $admin, $run] = pipelineApprovalFixture(TeamRole::ADMIN);

    $this->actingAs($admin)
        ->postJson("/api/v1/pipeline-runs/{$run->id}/reject", [
            'reason' => 'Not ready for production',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', PipelineRunStatus::FAILED->value);

    $deployment = Deployment::query()->find($run->deployment_id);

    expect($deployment?->status)->toBe(DeploymentStatus::FAILED)
        ->and(AuditLog::query()->where('operation', 'pipeline_run.rejected')->exists())->toBeTrue();

    Event::assertDispatched(PipelineRejected::class);
});

it('forbids developers from approving admin gated pipeline runs', function (): void {
    [$organization, , $run] = pipelineApprovalFixture(TeamRole::ADMIN);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->postJson("/api/v1/pipeline-runs/{$run->id}/approve")
        ->assertForbidden();
});
