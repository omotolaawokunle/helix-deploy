<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Python;

use App\Modules\Sites\Enums\PythonWSGI;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Steps\BaseDeploymentStep;

final class ReloadPythonProcessStep extends BaseDeploymentStep
{
    public function name(): string
    {
        return 'reload-python-process';
    }

    public function run(DeploymentContext $ctx): void
    {
        $service = match ($ctx->site->python_wsgi) {
            PythonWSGI::GUNICORN => 'gunicorn-'.$ctx->site->domain,
            PythonWSGI::UVICORN => 'uvicorn-'.$ctx->site->domain,
            default => 'gunicorn-'.$ctx->site->domain,
        };

        $this->runCommand($ctx, 'sudo systemctl reload '.$this->shellQuote($service));
    }
}
