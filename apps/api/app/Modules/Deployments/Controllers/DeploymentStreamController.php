<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DeploymentStreamController extends Controller
{
    public function stream(string $deployment): StreamedResponse
    {
        $deploymentModel = $this->resolveDeployment($deployment);
        $this->authorize('viewLogs', $deploymentModel);

        $deploymentModel->load(['steps' => fn ($query) => $query->orderBy('order')]);

        return response()->stream(
            function () use ($deploymentModel): void {
                $this->emitCatchUp($deploymentModel);

                if ($deploymentModel->status->isTerminal()) {
                    $this->emitTerminalEvent($deploymentModel);

                    return;
                }

                $this->subscribeToLiveEvents((string) $deploymentModel->getKey());
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function emitCatchUp(Deployment $deployment): void
    {
        foreach ($deployment->steps as $step) {
            $this->writeSseEvent('step.started', [
                'stepId' => (string) $step->getKey(),
                'name' => $step->name,
                'order' => $step->order,
                'status' => $step->status->value,
            ]);

            $output = (string) ($step->output ?? '');
            if ($output !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $this->writeSseEvent('log.line', [
                        'stepId' => (string) $step->getKey(),
                        'line' => $line,
                        'timestamp' => $step->finished_at?->toIso8601String()
                            ?? $step->started_at?->toIso8601String()
                            ?? now()->toIso8601String(),
                    ]);
                }
            }

            if ($this->stepIsCompleted($step)) {
                $this->writeSseEvent('step.completed', [
                    'stepId' => (string) $step->getKey(),
                    'status' => $step->status->value,
                    'duration' => $this->stepDuration($step),
                ]);
            }
        }
    }

    private function subscribeToLiveEvents(string $deploymentId): void
    {
        $channel = 'deployment.'.$deploymentId;
        $client = Redis::connection()->client();

        if (! $client instanceof \Redis) {
            return;
        }

        $client->setOption(\Redis::OPT_READ_TIMEOUT, -1);

        $client->subscribe([$channel], function (\Redis $redis, string $chan, string $message) use ($channel): void {
            if (connection_aborted()) {
                $redis->unsubscribe([$channel]);

                return;
            }

            $payload = json_decode($message, true);
            if (! is_array($payload) || ! isset($payload['event'], $payload['data'])) {
                return;
            }

            $this->writeSseEvent((string) $payload['event'], (array) $payload['data']);

            if (in_array($payload['event'], ['deployment.completed', 'deployment.rolled_back', 'deployment.cancelled'], true)) {
                $redis->unsubscribe([$channel]);
            }
        });
    }

    private function emitTerminalEvent(Deployment $deployment): void
    {
        if (
            $deployment->type === DeploymentType::ROLLBACK
            && $deployment->status === DeploymentStatus::SUCCESS
        ) {
            $activeRelease = $deployment->releases()->where('is_active', true)->first();

            $this->writeSseEvent('deployment.rolled_back', [
                'deploymentId' => (string) $deployment->getKey(),
                'siteId' => (string) $deployment->site_id,
                'rollbackTargetId' => $deployment->rollback_target_id,
                'status' => $deployment->status->value,
                'duration' => $deployment->duration(),
                'releaseId' => $activeRelease !== null ? (string) $activeRelease->getKey() : null,
                'releasePath' => $deployment->release_path,
                'commitHash' => $deployment->commit_hash,
            ]);

            return;
        }

        $this->emitDeploymentCompleted($deployment);
    }

    private function emitDeploymentCompleted(Deployment $deployment): void
    {
        $activeRelease = $deployment->releases()->where('is_active', true)->first();

        $this->writeSseEvent('deployment.completed', [
            'status' => $deployment->status->value,
            'duration' => $deployment->duration(),
            'releaseId' => $activeRelease !== null ? (string) $activeRelease->getKey() : null,
            'commitHash' => $deployment->commit_hash,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeSseEvent(string $event, array $data): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function stepIsCompleted(DeploymentStep $step): bool
    {
        return in_array($step->status, [
            DeploymentStepStatus::SUCCESS,
            DeploymentStepStatus::FAILED,
            DeploymentStepStatus::SKIPPED,
        ], true);
    }

    private function stepDuration(DeploymentStep $step): ?float
    {
        if ($step->started_at === null || $step->finished_at === null) {
            return null;
        }

        return (float) $step->finished_at->floatDiffInSeconds($step->started_at);
    }

    private function resolveDeployment(string $deploymentId): Deployment
    {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($deploymentId)
            ->first();

        if ($deployment === null) {
            throw (new ModelNotFoundException())->setModel(Deployment::class, [$deploymentId]);
        }

        return $deployment;
    }
}
