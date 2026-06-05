<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\DTOs;

use App\Modules\Deployments\Models\Deployment;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

final class PipelineExecutionContext
{
    public function __construct(
        public readonly PipelineRun $run,
        public readonly Site $site,
        public readonly Server $server,
        public readonly Deployment $deployment,
        public readonly ?SSHConnectionInterface $ssh,
        public readonly string $actorId,
    ) {
    }

    public function appendOutput(string $line): void
    {
        // Output is persisted per step by the orchestrator.
    }
}
