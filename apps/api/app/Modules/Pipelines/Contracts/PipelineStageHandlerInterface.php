<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Contracts;

use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Models\PipelineRunStep;

interface PipelineStageHandlerInterface
{
    public function type(): PipelineStepType;

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult;
}
