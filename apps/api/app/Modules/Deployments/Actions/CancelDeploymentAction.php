<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Models\Deployment;
use InvalidArgumentException;

class CancelDeploymentAction
{
    public function execute(Deployment $deployment, User $actor): Deployment
    {
        if (! in_array($deployment->status, [DeploymentStatus::PENDING, DeploymentStatus::RUNNING], true)) {
            throw new InvalidArgumentException('Only pending or running deployments can be cancelled.');
        }

        $beforeState = [
            'status' => $deployment->status->value,
        ];

        $deployment->forceFill([
            'status' => DeploymentStatus::CANCELLED,
            'finished_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'deployment.cancelled',
            resource: $deployment,
            beforeState: $beforeState,
            afterState: [
                'status' => DeploymentStatus::CANCELLED->value,
                'cancelledBy' => (string) $actor->getKey(),
            ],
        );

        return $deployment->refresh();
    }
}
