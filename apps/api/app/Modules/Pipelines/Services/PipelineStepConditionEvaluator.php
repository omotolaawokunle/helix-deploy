<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Services;

use App\Modules\Pipelines\Models\PipelineRunStep;
use App\Modules\Sites\Models\Site;

class PipelineStepConditionEvaluator
{
    public function shouldRun(PipelineRunStep $step, Site $site): bool
    {
        $config = $step->config ?? [];
        $environment = $config['environment'] ?? null;

        if (! is_string($environment) || trim($environment) === '') {
            return true;
        }

        $site->loadMissing('environment');

        $siteEnvironment = $site->environment?->name ?? $site->environment?->label;

        if ($siteEnvironment === null || $siteEnvironment === '') {
            return false;
        }

        return strtolower($siteEnvironment) === strtolower($environment);
    }
}
