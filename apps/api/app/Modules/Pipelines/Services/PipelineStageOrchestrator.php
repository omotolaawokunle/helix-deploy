<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineRunStatus;
use App\Modules\Pipelines\Enums\PipelineRunStepStatus;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Exceptions\PipelineStageFailedException;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\DB;

class PipelineStageOrchestrator
{
    public function __construct(
        private readonly PipelineStepConditionEvaluator $conditionEvaluator,
        private readonly PipelineStageHandlerRegistry $handlerRegistry,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function run(PipelineRun $run, string $actorId, int $startStepIndex = 0): void
    {
        $run = PipelineRun::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with(['steps', 'site.server', 'site.environment', 'deployment'])
            ->whereKey($run->getKey())
            ->firstOrFail();

        if ($run->status->isTerminal()) {
            return;
        }

        if ($run->status === PipelineRunStatus::AWAITING_APPROVAL) {
            return;
        }

        $site = $run->site;
        $deployment = $run->deployment;
        $server = $site?->server;

        abort_if($site === null, 404, 'Pipeline site not found.');
        abort_if($deployment === null, 404, 'Pipeline deployment not found.');
        abort_if($server === null, 404, 'Pipeline server not found.');
        abort_if($server->credential_id === null, 422, 'Server SSH credential is required for pipeline execution.');

        if ($run->started_at === null) {
            $run->forceFill([
                'status' => PipelineRunStatus::RUNNING,
                'started_at' => now(),
            ])->save();

            AuditLog::record(
                operation: 'pipeline_run.started',
                resource: $run,
                afterState: [
                    'pipelineId' => $run->pipeline_id,
                    'siteId' => $run->site_id,
                    'deploymentId' => $run->deployment_id,
                ],
            );
        } elseif ($run->status === PipelineRunStatus::PENDING) {
            $run->forceFill(['status' => PipelineRunStatus::RUNNING])->save();
        }

        $steps = $run->steps->sortBy('order')->values();
        $needsSsh = $this->upcomingStepsNeedSsh($steps, $startStepIndex, $site);

        $connection = $needsSsh ? $this->sshManager->connect($server, $this->credentialVault) : null;

        if ($connection !== null) {
            $connection->connect();
        }

        try {
            $context = new PipelineExecutionContext(
                run: $run,
                site: $site,
                server: $server,
                deployment: $deployment,
                ssh: $connection,
                actorId: $actorId,
            );

            for ($index = $startStepIndex; $index < $steps->count(); $index++) {
                /** @var PipelineRunStep $step */
                $step = $steps[$index];

                if ($step->status === PipelineRunStepStatus::SUCCESS
                    || $step->status === PipelineRunStepStatus::SKIPPED) {
                    continue;
                }

                if (! $this->conditionEvaluator->shouldRun($step, $site)) {
                    $this->markStepSkipped($step);
                    $run->forceFill(['current_step_order' => $index])->save();

                    continue;
                }

                $run->forceFill(['current_step_order' => $index])->save();

                $result = $this->executeStepWithRetries($context, $step);

                if ($result === PipelineStageResult::PAUSED) {
                    return;
                }
            }

            $this->markRunSuccessful($run, $deployment);
        } catch (PipelineStageFailedException $exception) {
            $this->markRunFailed($run, $deployment, $exception);

            throw $exception;
        } finally {
            $connection?->disconnect();
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, PipelineRunStep> $steps
     */
    private function upcomingStepsNeedSsh($steps, int $startStepIndex, \App\Modules\Sites\Models\Site $site): bool
    {
        foreach ($steps as $index => $step) {
            if ($index < $startStepIndex) {
                continue;
            }

            if ($step->status === PipelineRunStepStatus::SUCCESS
                || $step->status === PipelineRunStepStatus::SKIPPED) {
                continue;
            }

            if (! $this->conditionEvaluator->shouldRun($step, $site)) {
                continue;
            }

            if (in_array($step->type->value, ['migrate', 'health_check', 'script'], true)) {
                return true;
            }
        }

        return false;
    }

    private function executeStepWithRetries(
        PipelineExecutionContext $context,
        PipelineRunStep $step,
    ): PipelineStageResult {
        $maxAttempts = max(1, $step->retry_attempts + 1);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $step->forceFill([
                'status' => PipelineRunStepStatus::RUNNING,
                'attempts_made' => $attempt,
                'started_at' => $step->started_at ?? now(),
                'finished_at' => null,
            ])->save();

            try {
                $handler = $this->handlerRegistry->forType($step->type);
                $result = $handler->handle($context, $step);

                if ($result === PipelineStageResult::PAUSED) {
                    return PipelineStageResult::PAUSED;
                }

                $step->forceFill([
                    'status' => PipelineRunStepStatus::SUCCESS,
                    'exit_code' => 0,
                    'finished_at' => now(),
                ])->save();

                return PipelineStageResult::COMPLETED;
            } catch (PipelineStageFailedException $exception) {
                $lastException = $exception;

                if ($attempt >= $maxAttempts) {
                    $step->forceFill([
                        'status' => PipelineRunStepStatus::FAILED,
                        'exit_code' => $exception->exitCode,
                        'output' => $exception->getMessage(),
                        'finished_at' => now(),
                    ])->save();

                    throw $exception;
                }

                sleep(min(30, 2 ** $attempt));
            }
        }

        throw $lastException ?? new PipelineStageFailedException($step->name, 'Stage failed after retries.');
    }

    private function markStepSkipped(PipelineRunStep $step): void
    {
        $step->forceFill([
            'status' => PipelineRunStepStatus::SKIPPED,
            'finished_at' => now(),
        ])->save();
    }

    private function markRunSuccessful(PipelineRun $run, \App\Modules\Deployments\Models\Deployment $deployment): void
    {
        DB::transaction(function () use ($run, $deployment): void {
            $run->forceFill([
                'status' => PipelineRunStatus::SUCCESS,
                'finished_at' => now(),
            ])->save();

            if (! $deployment->status->isTerminal() && $deployment->status !== DeploymentStatus::SUCCESS) {
                $deployment->forceFill([
                    'status' => DeploymentStatus::SUCCESS,
                    'finished_at' => now(),
                ])->save();
            }

            AuditLog::record(
                operation: 'pipeline_run.completed',
                resource: $run,
                afterState: [
                    'status' => PipelineRunStatus::SUCCESS->value,
                    'deploymentId' => $deployment->getKey(),
                ],
            );
        });
    }

    private function markRunFailed(
        PipelineRun $run,
        \App\Modules\Deployments\Models\Deployment $deployment,
        PipelineStageFailedException $exception,
    ): void {
        DB::transaction(function () use ($run, $deployment, $exception): void {
            $run->forceFill([
                'status' => PipelineRunStatus::FAILED,
                'finished_at' => now(),
            ])->save();

            if (! $deployment->status->isTerminal()) {
                $deployment->forceFill([
                    'status' => DeploymentStatus::FAILED,
                    'finished_at' => now(),
                ])->save();
            }

            AuditLog::record(
                operation: 'pipeline_run.failed',
                resource: $run,
                afterState: [
                    'status' => PipelineRunStatus::FAILED->value,
                    'step' => $exception->stepName,
                    'message' => $exception->getMessage(),
                ],
            );
        });
    }
}
