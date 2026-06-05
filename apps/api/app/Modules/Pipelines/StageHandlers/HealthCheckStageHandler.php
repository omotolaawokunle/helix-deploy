<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Exceptions\PipelineStageFailedException;
use App\Modules\Pipelines\Models\PipelineRunStep;

class HealthCheckStageHandler implements PipelineStageHandlerInterface
{
    public function type(): PipelineStepType
    {
        return PipelineStepType::HEALTH_CHECK;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        $config = $step->config ?? [];
        $url = is_string($config['url'] ?? null) && $config['url'] !== ''
            ? (string) $config['url']
            : 'https://'.$context->site->domain;
        $timeout = is_numeric($config['timeout'] ?? null) ? (int) $config['timeout'] : 30;
        $timeout = max(1, min($timeout, 120));

        $ssh = $context->ssh;

        if ($ssh === null) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: 'SSH connection is required for health check stages.',
            );
        }

        $command = sprintf(
            'curl -sf -o /dev/null -w "%%{http_code}" --max-time %d %s',
            $timeout,
            escapeshellarg($url),
        );

        $result = $ssh->run($command);

        if ($result->failed()) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: 'Health check failed for '.$url,
                exitCode: $result->exitCode,
            );
        }

        return PipelineStageResult::COMPLETED;
    }
}
