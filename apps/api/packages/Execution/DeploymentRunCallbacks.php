<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Packages\Execution\Contracts\DeploymentStepInterface;

final class DeploymentRunCallbacks
{
    public function __construct(
        private readonly ?\Closure $onStepStarting = null,
        private readonly ?\Closure $onStepFinished = null,
        private readonly ?\Closure $onStepSkipped = null,
    ) {
    }

    public function stepStarting(DeploymentStepInterface $step, int $order): void
    {
        if ($this->onStepStarting !== null) {
            ($this->onStepStarting)($step, $order);
        }
    }

    public function stepFinished(DeploymentStepInterface $step, int $order): void
    {
        if ($this->onStepFinished !== null) {
            ($this->onStepFinished)($step, $order);
        }
    }

    public function stepSkipped(DeploymentStepInterface $step, int $order): void
    {
        if ($this->onStepSkipped !== null) {
            ($this->onStepSkipped)($step, $order);
        }
    }
}
