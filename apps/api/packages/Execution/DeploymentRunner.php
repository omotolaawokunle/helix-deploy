<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentStepUpdated;
use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Packages\Execution\Contracts\DeploymentStepInterface;
use App\Packages\Execution\Contracts\ExecutionRunnerInterface;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;

class DeploymentRunner implements ExecutionRunnerInterface
{
    /**
     * @param list<DeploymentStepInterface> $steps
     */
    public function run(DeploymentContext $ctx, array $steps, ?DeploymentRunCallbacks $callbacks = null): void
    {
        /** @var list<DeploymentStepInterface> $completed */
        $completed = [];

        try {
            foreach ($steps as $index => $step) {
                if ($step->isSkippable($ctx)) {
                    $this->markStepSkipped($ctx, $step, $index);
                    $callbacks?->stepSkipped($step, $index);

                    continue;
                }

                $callbacks?->stepStarting($step, $index);
                $this->runStep($ctx, $step, $index);
                $callbacks?->stepFinished($step, $index);
                $completed[] = $step;
            }
        } catch (DeploymentStepFailedException $exception) {
            $this->rollbackCompleted($ctx, $completed);
            $ctx->flushLog();

            throw $exception;
        }

        $ctx->flushLog();
    }

    private function runStep(DeploymentContext $ctx, DeploymentStepInterface $step, int $order): void
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

    private function markStepSkipped(DeploymentContext $ctx, DeploymentStepInterface $step, int $order): void
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
        DeploymentContext $ctx,
        DeploymentStepInterface $step,
        int $order,
    ): ?DeploymentStepRecord {
        return $ctx->deployment->steps()
            ->where('name', $step->name())
            ->where('order', $order)
            ->first();
    }

    private function emitStepUpdated(DeploymentContext $ctx, DeploymentStepRecord $record): void
    {
        event(new DeploymentStepUpdated($ctx->deployment, $record->refresh()));
    }

    /**
     * @param list<DeploymentStepInterface> $completed
     */
    private function rollbackCompleted(DeploymentContext $ctx, array $completed): void
    {
        foreach (array_reverse($completed) as $step) {
            $step->rollback($ctx);
        }
    }
}
