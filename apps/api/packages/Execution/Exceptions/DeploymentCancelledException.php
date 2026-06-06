<?php

declare(strict_types=1);

namespace App\Packages\Execution\Exceptions;

use RuntimeException;

final class DeploymentCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Deployment was cancelled.');
    }
}
