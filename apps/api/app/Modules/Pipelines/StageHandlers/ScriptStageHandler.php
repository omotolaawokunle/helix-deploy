<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Exceptions\PipelineStageFailedException;
use App\Modules\Pipelines\Models\PipelineRunStep;

class ScriptStageHandler implements PipelineStageHandlerInterface
{
    private const TIMEOUT_SECONDS = 600;

    public function type(): PipelineStepType
    {
        return PipelineStepType::SCRIPT;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        $config = $step->config ?? [];
        $script = is_string($config['script'] ?? null) ? trim((string) $config['script']) : '';

        if ($script === '') {
            return PipelineStageResult::COMPLETED;
        }

        $ssh = $context->ssh;

        if ($ssh === null) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: 'SSH connection is required for script stages.',
            );
        }

        $releasePath = $context->deployment->release_path
            ?? '/var/www/'.$context->site->domain.'/current';

        $command = 'cd '.escapeshellarg($releasePath).' && '.$script;
        $result = $ssh->run($command, null, self::TIMEOUT_SECONDS);

        if ($result->failed()) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: $result->output(),
                exitCode: $result->exitCode,
            );
        }

        return PipelineStageResult::COMPLETED;
    }
}
