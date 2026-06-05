<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Exceptions;

use RuntimeException;

class NoBuildRunnerAvailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No build runner with available capacity is available for this site.');
    }

    public function retryAfterSeconds(): int
    {
        return 30;
    }
}
