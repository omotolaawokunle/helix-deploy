<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Exceptions;

use RuntimeException;

class PipelineStageFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $stepName,
        string $message,
        public readonly ?int $exitCode = null,
    ) {
        parent::__construct($message);
    }
}
