<?php

declare(strict_types=1);

namespace App\Listeners\Deployments;

use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Events\DeploymentLogLine;
use App\Modules\Deployments\Events\DeploymentStepUpdated;
use App\Packages\Realtime\DeploymentStreamPublisher;

final class PublishDeploymentStreamMessage
{
    public function __construct(
        private readonly DeploymentStreamPublisher $publisher,
    ) {
    }

    public function handle(DeploymentLogLine|DeploymentStepUpdated|DeploymentCompleted $event): void
    {
        if ($event instanceof DeploymentLogLine) {
            $this->publisher->publish(
                (string) $event->deployment->getKey(),
                'log.line',
                $event->streamPayload(),
            );

            return;
        }

        if ($event instanceof DeploymentStepUpdated) {
            $this->publisher->publish(
                (string) $event->deployment->getKey(),
                $event->sseEventName(),
                $event->streamPayload(),
            );

            return;
        }

        $deployment = $event->deployment;
        $activeRelease = $deployment->releases()->where('is_active', true)->first();

        $this->publisher->publish(
            (string) $deployment->getKey(),
            'deployment.completed',
            [
                'status' => $deployment->status->value,
                'duration' => $deployment->duration(),
                'releaseId' => $activeRelease !== null ? (string) $activeRelease->getKey() : null,
                'commitHash' => $deployment->commit_hash,
            ],
        );
    }
}
