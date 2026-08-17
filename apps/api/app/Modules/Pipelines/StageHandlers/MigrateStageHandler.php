<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\StageHandlers;

use App\Modules\Pipelines\Contracts\PipelineStageHandlerInterface;
use App\Modules\Pipelines\DTOs\PipelineExecutionContext;
use App\Modules\Pipelines\Enums\PipelineStageResult;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Exceptions\PipelineStageFailedException;
use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Sites\Services\SiteDeployPathResolver;
use App\Packages\Execution\Support\ArtisanMigrateShellCommand;

class MigrateStageHandler implements PipelineStageHandlerInterface
{
    public function type(): PipelineStepType
    {
        return PipelineStepType::MIGRATE;
    }

    public function handle(PipelineExecutionContext $context, PipelineRunStep $step): PipelineStageResult
    {
        if (! $context->site->run_migrations) {
            return PipelineStageResult::COMPLETED;
        }

        $ssh = $context->ssh;

        if ($ssh === null) {
            throw new PipelineStageFailedException(
                stepName: $step->name,
                message: 'SSH connection is required for migrate stages.',
            );
        }

        $releasePath = $context->deployment->release_path
            ?? app(SiteDeployPathResolver::class)->currentPath($context->site);

        $command = ArtisanMigrateShellCommand::forReleasePath($releasePath);
        $result = $ssh->run($command);

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
