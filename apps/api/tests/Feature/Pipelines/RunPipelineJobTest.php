<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Jobs\RunPipelineJob;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Pipelines\Models\PipelineStep;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

it('runs a three stage pipeline with conditional skip and FakeSSHConnection', function (): void {
    [$organization, $server, $site, $user] = pipelineRunFixture();

    $credential = \App\Modules\Credentials\Models\Credential::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'credentialable_type' => null,
        'credentialable_id' => null,
        'type' => 'ssh_private_key',
        'name' => 'Pipeline SSH Key',
        'encrypted_value' => 'ciphertext',
        'nonce' => 'nonce',
        'key_fingerprint' => 'fp',
        'created_by' => (string) $user->getKey(),
        'last_used_at' => null,
    ]);
    $server->forceFill(['credential_id' => (string) $credential->getKey()])->save();

    $pipeline = Pipeline::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Integration Pipeline',
        'description' => null,
        'stages' => [],
        'created_by' => (string) $user->getKey(),
    ]);

    PipelineStep::query()->create([
        'pipeline_id' => (string) $pipeline->getKey(),
        'name' => 'Warmup script',
        'type' => PipelineStepType::SCRIPT,
        'order' => 0,
        'config' => ['script' => 'echo pipeline-ok'],
        'requires_approval' => false,
        'retry_attempts' => 0,
    ]);
    PipelineStep::query()->create([
        'pipeline_id' => (string) $pipeline->getKey(),
        'name' => 'Staging migrate',
        'type' => PipelineStepType::MIGRATE,
        'order' => 1,
        'config' => ['environment' => 'staging'],
        'requires_approval' => false,
        'retry_attempts' => 0,
    ]);
    PipelineStep::query()->create([
        'pipeline_id' => (string) $pipeline->getKey(),
        'name' => 'Notify team',
        'type' => PipelineStepType::NOTIFY,
        'order' => 2,
        'config' => ['channel' => 'audit', 'message' => 'done'],
        'requires_approval' => false,
        'retry_attempts' => 0,
    ]);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::PENDING,
        'triggered_by' => (string) $user->getKey(),
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $pipelineRun = PipelineRun::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'pipeline_id' => (string) $pipeline->getKey(),
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $deployment->getKey(),
        'triggered_by' => (string) $user->getKey(),
        'status' => PipelineRunStatus::PENDING,
        'current_step_order' => 0,
        'metadata' => [],
    ]);

    $deployment->forceFill(['pipeline_run_id' => (string) $pipelineRun->getKey()])->save();

    $templateSteps = PipelineStep::query()
        ->where('pipeline_id', (string) $pipeline->getKey())
        ->orderBy('order')
        ->get();

    foreach ($templateSteps as $templateStep) {
        PipelineRunStep::query()->create([
            'pipeline_run_id' => (string) $pipelineRun->getKey(),
            'pipeline_step_id' => (string) $templateStep->getKey(),
            'name' => $templateStep->name,
            'type' => $templateStep->type,
            'order' => $templateStep->order,
            'status' => PipelineRunStepStatus::PENDING,
            'config' => $templateStep->config,
            'requires_approval' => false,
            'retry_attempts' => 0,
        ]);
    }

    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('*echo pipeline-ok*', new SSHResult('echo pipeline-ok', 0, "pipeline-ok\n", '', 0.01));

    $this->mock(SSHManager::class, function ($mock) use ($server, $fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $job = new RunPipelineJob((string) $pipelineRun->getKey(), (string) $user->getKey());
    $job->handle(app(\App\Modules\Pipelines\Services\PipelineStageOrchestrator::class));

    $pipelineRun->refresh();
    $steps = PipelineRunStep::query()
        ->where('pipeline_run_id', (string) $pipelineRun->getKey())
        ->orderBy('order')
        ->get();

    expect($pipelineRun->status)->toBe(PipelineRunStatus::SUCCESS)
        ->and($steps[0]->status)->toBe(PipelineRunStepStatus::SUCCESS)
        ->and($steps[1]->status)->toBe(PipelineRunStepStatus::SKIPPED)
        ->and($steps[2]->status)->toBe(PipelineRunStepStatus::SUCCESS);

    expect(\App\Modules\Audit\Models\AuditLog::query()->where('operation', 'pipeline_run.started')->exists())->toBeTrue()
        ->and(\App\Modules\Audit\Models\AuditLog::query()->where('operation', 'pipeline_run.completed')->exists())->toBeTrue()
        ->and(\App\Modules\Audit\Models\AuditLog::query()->where('operation', 'pipeline.notify')->exists())->toBeTrue();
});

/**
 * @return array{0: \App\Modules\Organizations\Models\Organization, 1: \App\Modules\Servers\Models\Server, 2: \App\Modules\Sites\Models\Site, 3: User}
 */
function pipelineRunFixture(): array
{
    [$organization, $server, $site, $deployment] = executionFixture();

    $user = User::query()->findOrFail($deployment->triggered_by);
    $organization->users()->attach($user->getKey(), ['role' => 'owner']);

    return [$organization, $server, $site, $user];
}
