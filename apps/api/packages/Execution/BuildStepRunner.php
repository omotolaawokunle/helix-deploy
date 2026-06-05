<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Modules\Deployments\Enums\DeploymentStepPhase;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentStepUpdated;
use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Modules\Deployments\Services\DeploymentCancellationService;
use App\Packages\Execution\Contracts\BuildStepInterface;
use App\Packages\Execution\Exceptions\DeploymentCancelledException;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;

final class BuildStepRunner
{
    public function __construct(
        private readonly ?DeploymentCancellationService $cancellation = null,
    ) {
    }

    /**
     * @param list<BuildStepInterface> $steps
     */
    public function run(BuildContext $ctx, array $steps): void
    {
        try {
            foreach ($steps as $index => $step) {
                $this->assertNotCancelled($ctx);

                if ($step->isSkippable($ctx)) {
                    $this->markStepSkipped($ctx, $step, $index);

                    continue;
                }

                $this->runStep($ctx, $step, $index);
            }
        } catch (DeploymentCancelledException $exception) {
            $ctx->flushLog();

            throw $exception;
        } catch (DeploymentStepFailedException $exception) {
            $ctx->flushLog();

            throw $exception;
        }

        $ctx->flushLog();
    }

    private function runStep(BuildContext $ctx, BuildStepInterface $step, int $order): void
    {
        $record = $this->resolveStepRecord($ctx, $step, $order);

        if ($record !== null) {
            $record->forceFill([
                'status' => DeploymentStepStatus::RUNNING,
                'started_at' => now(),
            ])->save();
            $ctx->currentStepRecord = $record;
            $this->emitStepUpdated($ctx, $record);
        }

        try {
            $step->run($ctx);
            $ctx->flushLog();

            if ($record !== null) {
                $record->forceFill([
                    'status' => DeploymentStepStatus::SUCCESS,
                    'exit_code' => 0,
                    'finished_at' => now(),
                ])->save();
                $this->emitStepUpdated($ctx, $record);
            }
        } catch (DeploymentStepFailedException $exception) {
            if ($record !== null) {
                $record->forceFill([
                    'status' => DeploymentStepStatus::FAILED,
                    'exit_code' => $exception->result->exitCode,
                    'finished_at' => now(),
                ])->save();
                $this->emitStepUpdated($ctx, $record);
            }

            throw $exception;
        } finally {
            $ctx->currentStepRecord = null;
        }
    }

    private function markStepSkipped(BuildContext $ctx, BuildStepInterface $step, int $order): void
    {
        $record = $this->resolveStepRecord($ctx, $step, $order);

        if ($record === null) {
            return;
        }

        $record->forceFill([
            'status' => DeploymentStepStatus::SKIPPED,
            'exit_code' => null,
            'finished_at' => now(),
        ])->save();

        $ctx->currentStepRecord = $record;
        $this->emitStepUpdated($ctx, $record);
        $ctx->currentStepRecord = null;
    }

    private function resolveStepRecord(
        BuildContext $ctx,
        BuildStepInterface $step,
        int $order,
    ): ?DeploymentStepRecord {
        return $ctx->deployment->steps()
            ->where('name', $step->name())
            ->where('phase', DeploymentStepPhase::BUILD->value)
            ->where('order', $order)
            ->first();
    }

    private function emitStepUpdated(BuildContext $ctx, DeploymentStepRecord $record): void
    {
        event(new DeploymentStepUpdated($ctx->deployment, $record->refresh()));
    }

    private function assertNotCancelled(BuildContext $ctx): void
    {
        if ($this->cancellation === null) {
            return;
        }

        if ($this->cancellation->isRequested((string) $ctx->deployment->getKey())) {
            $ctx->ssh->interrupt();
            throw new DeploymentCancelledException();
        }
    }
}
