<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Services;

use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\Enums\PipelineStepType;
use InvalidArgumentException;

class PipelineStageHandlerRegistry
{
    /**
     * @param iterable<PipelineStageHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
    ) {
    }

    public function forType(PipelineStepType $type): PipelineStageHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        throw new InvalidArgumentException(sprintf('No handler registered for pipeline step type [%s].', $type->value));
    }
}
