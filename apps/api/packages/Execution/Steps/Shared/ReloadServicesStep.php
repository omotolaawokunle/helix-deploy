<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Shared;

use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\Contracts\DeploymentStepInterface;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;
use App\Packages\Execution\Steps\Docker\DockerComposeUpStep;
use App\Packages\Execution\Steps\Go\RestartGoServiceStep;
use App\Packages\Execution\Steps\NodeJS\ReloadPM2Step;
use App\Packages\Execution\Steps\PHP\ReloadPHPFPMStep;
use App\Packages\Execution\Steps\PHP\RestartWorkersStep;
use App\Packages\Execution\Steps\Python\ReloadPythonProcessStep;
use InvalidArgumentException;

final class ReloadServicesStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'reload-services';
    }

    public function run(DeploymentContext $ctx): void
    {
        foreach ($this->reloadSteps($ctx) as $step) {
            if ($step->isSkippable($ctx)) {
                continue;
            }

            $step->run($ctx);
        }
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function reloadSteps(DeploymentContext $ctx): array
    {
        return match ($ctx->site->runtime) {
            Runtime::PHP => [new ReloadPHPFPMStep(), new RestartWorkersStep()],
            Runtime::NODEJS => [new ReloadPM2Step()],
            Runtime::PYTHON => [new ReloadPythonProcessStep()],
            Runtime::GO => [new RestartGoServiceStep()],
            Runtime::DOCKER => [new DockerComposeUpStep()],
            default => throw new InvalidArgumentException('Unsupported runtime for rollback reload: '.$ctx->site->runtime->value),
        };
    }
}
