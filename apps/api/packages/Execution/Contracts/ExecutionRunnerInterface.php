<?php

declare(strict_types=1);

namespace App\Packages\Execution\Contracts;

use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\DeploymentRunCallbacks;

interface ExecutionRunnerInterface
{
    /**
     * @param list<DeploymentStepInterface> $steps
     */
    public function run(DeploymentContext $ctx, array $steps, ?DeploymentRunCallbacks $callbacks = null): void;
}
