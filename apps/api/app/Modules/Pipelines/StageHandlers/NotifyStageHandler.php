<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Models\PipelineRunStep;

class NotifyStageHandler implements PipelineStageHandlerInterface
{
    public function type(): PipelineStepType
    {
        return PipelineStepType::NOTIFY;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        $config = $step->config ?? [];

        AuditLog::record(
            operation: 'pipeline.notify',
            resource: $context->run,
            metadata: [
                'stepId' => (string) $step->getKey(),
                'channel' => $config['channel'] ?? null,
                'message' => $config['message'] ?? null,
            ],
        );

        return PipelineStageResult::COMPLETED;
    }
}
