<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\NodePM;
use App\Modules\Sites\Enums\PythonWSGI;
use App\Modules\Sites\Enums\Runtime;

readonly class CreateSiteDTO
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public string $domain,
        public array $aliases,
        public string $webroot,
        public Runtime $runtime,
        public DeployMode $deployMode,
        public ?string $repositoryUrl,
        public ?string $repositoryProvider,
        public string $deployBranch,
        public ?string $deployScript,
        public bool $runMigrations,
        public ?string $dockerImage,
        public ?string $dockerRegistry,
        public string $dockerComposePath,
        public ?DockerBuildMode $dockerBuildMode,
        public ?string $phpVersion,
        public ?NodePM $nodePm,
        public ?PythonWSGI $pythonWsgi,
        public ?string $goBinaryPath,
        public ?string $goServiceName,
        public ?int $appPort,
        public ?string $projectId,
        public ?string $environmentId,
        public ?string $pipelineId,
    ) {
    }
}
