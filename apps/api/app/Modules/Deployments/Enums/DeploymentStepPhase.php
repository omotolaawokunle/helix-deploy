<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Enums;

enum DeploymentStepPhase: string
{
    case BUILD = 'build';
    case DEPLOY = 'deploy';
}
