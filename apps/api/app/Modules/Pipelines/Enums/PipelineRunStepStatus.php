<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Enums;

enum PipelineRunStepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case AWAITING_APPROVAL = 'awaiting_approval';
}
