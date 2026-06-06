<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Enums;

enum PipelineStageResult
{
    case COMPLETED;
    case PAUSED;
}
