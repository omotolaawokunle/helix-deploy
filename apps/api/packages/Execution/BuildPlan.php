<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Packages\Execution\Contracts\BuildStepInterface;
use App\Packages\Execution\Contracts\DeploymentStepInterface;

final readonly class BuildPlan
{
    /**
     * @param list<BuildStepInterface> $buildSteps
     * @param list<DeploymentStepInterface> $deploySteps
     */
    public function __construct(
        public array $buildSteps,
        public array $deploySteps,
    ) {
    }

    public function usesRunnerBuild(): bool
    {
        return $this->buildSteps !== [];
    }
}
