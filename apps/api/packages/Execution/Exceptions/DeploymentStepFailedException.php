<?php

declare(strict_types=1);

namespace App\Packages\Execution\Exceptions;

use App\Packages\SSH\SSHResult;
use RuntimeException;

class DeploymentStepFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SSHResult $result,
        public readonly string $stepName,
    ) {
        parent::__construct($message);
    }
}
