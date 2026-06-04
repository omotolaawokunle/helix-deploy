<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Modules\Deployments\Models\Deployment;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;
use App\Packages\Execution\Contracts\DeploymentStepInterface;
use App\Packages\Execution\Steps\Docker\DockerBuildStep;
use App\Packages\Execution\Steps\Docker\DockerCleanupStep;
use App\Packages\Execution\Steps\Docker\DockerComposeUpStep;
use App\Packages\Execution\Steps\Docker\DockerLoginStep;
use App\Packages\Execution\Steps\Docker\DockerPullStep;
use App\Packages\Execution\Steps\Go\DownloadBinaryStep;
use App\Packages\Execution\Steps\Go\ReplaceBinaryStep;
use App\Packages\Execution\Steps\Go\RestartGoServiceStep;
use App\Packages\Execution\Steps\NodeJS\BuildNodeAssetsStep;
use App\Packages\Execution\Steps\NodeJS\InstallNpmDepsNodeStep;
use App\Packages\Execution\Steps\NodeJS\ReloadPM2Step;
use App\Packages\Execution\Steps\PHP\BuildAssetsStep;
use App\Packages\Execution\Steps\PHP\ClearCacheStep;
use App\Packages\Execution\Steps\PHP\InstallComposerDepsStep;
use App\Packages\Execution\Steps\PHP\InstallNpmDepsStep;
use App\Packages\Execution\Steps\PHP\ReloadPHPFPMStep;
use App\Packages\Execution\Steps\PHP\RestartWorkersStep;
use App\Packages\Execution\Steps\PHP\RunMigrationsStep;
use App\Packages\Execution\Steps\Python\CollectStaticStep;
use App\Packages\Execution\Steps\Python\InstallPythonDepsStep;
use App\Packages\Execution\Steps\Python\ReloadPythonProcessStep;
use App\Packages\Execution\Steps\Shared\ActivateReleaseStep;
use App\Packages\Execution\Steps\Shared\CleanupOldReleasesStep;
use App\Packages\Execution\Steps\Shared\CloneRepositoryStep;
use App\Packages\Execution\Steps\Shared\CreateReleaseDirectoryStep;
use App\Packages\Execution\Steps\Shared\LinkSharedDirectoriesStep;
use App\Packages\Execution\Steps\Shared\RunCustomScriptStep;
use App\Packages\Execution\Steps\Shared\ReloadServicesStep;
use App\Packages\Execution\Steps\Shared\VerifyConnectionStep;
use App\Packages\Execution\Steps\Shared\VerifyReleaseExistsStep;
use InvalidArgumentException;

final class PipelineBuilder
{
    /**
     * @return list<DeploymentStepInterface>
     */
    public function buildRollback(Site $site): array
    {
        return [
            new VerifyConnectionStep(),
            new VerifyReleaseExistsStep(),
            new ActivateReleaseStep(),
            new ReloadServicesStep(),
        ];
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    public function build(Site $site, Deployment $deployment): array
    {
        if ($site->runtime === Runtime::DOCKER) {
            return $this->buildDockerPipeline($site);
        }

        if ($site->deploy_mode !== DeployMode::GIT) {
            throw new InvalidArgumentException('Unsupported deploy mode: '.$site->deploy_mode->value);
        }

        return match ($site->runtime) {
            Runtime::PHP => $this->phpGitPipeline(),
            Runtime::NODEJS => $this->nodeGitPipeline(),
            Runtime::PYTHON => $this->pythonGitPipeline(),
            Runtime::GO => $this->goGitPipeline(),
            default => throw new InvalidArgumentException('Unsupported runtime: '.$site->runtime->value),
        };
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function phpGitPipeline(): array
    {
        return [
            new VerifyConnectionStep(),
            new CreateReleaseDirectoryStep(),
            new CloneRepositoryStep(),
            new InstallComposerDepsStep(),
            new InstallNpmDepsStep(),
            new BuildAssetsStep(),
            new LinkSharedDirectoriesStep(),
            new RunMigrationsStep(),
            new ClearCacheStep(),
            new ActivateReleaseStep(),
            new ReloadPHPFPMStep(),
            new RestartWorkersStep(),
            new RunCustomScriptStep(),
            new CleanupOldReleasesStep(),
        ];
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function nodeGitPipeline(): array
    {
        return [
            new VerifyConnectionStep(),
            new CreateReleaseDirectoryStep(),
            new CloneRepositoryStep(),
            new InstallNpmDepsNodeStep(),
            new BuildNodeAssetsStep(),
            new LinkSharedDirectoriesStep(),
            new ActivateReleaseStep(),
            new ReloadPM2Step(),
            new RunCustomScriptStep(),
            new CleanupOldReleasesStep(),
        ];
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function pythonGitPipeline(): array
    {
        return [
            new VerifyConnectionStep(),
            new CreateReleaseDirectoryStep(),
            new CloneRepositoryStep(),
            new InstallPythonDepsStep(),
            new CollectStaticStep(),
            new LinkSharedDirectoriesStep(),
            new ActivateReleaseStep(),
            new ReloadPythonProcessStep(),
            new RunCustomScriptStep(),
            new CleanupOldReleasesStep(),
        ];
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function goGitPipeline(): array
    {
        return [
            new VerifyConnectionStep(),
            new CreateReleaseDirectoryStep(),
            new CloneRepositoryStep(),
            new DownloadBinaryStep(),
            new LinkSharedDirectoriesStep(),
            new ActivateReleaseStep(),
            new ReplaceBinaryStep(),
            new RestartGoServiceStep(),
            new RunCustomScriptStep(),
            new CleanupOldReleasesStep(),
        ];
    }

    /**
     * @return list<DeploymentStepInterface>
     */
    private function buildDockerPipeline(Site $site): array
    {
        if ($site->docker_build_mode === DockerBuildMode::BUILD) {
            return [
                new VerifyConnectionStep(),
                new CreateReleaseDirectoryStep(),
                new CloneRepositoryStep(),
                new DockerBuildStep(),
                new DockerComposeUpStep(),
                new DockerCleanupStep(),
            ];
        }

        return [
            new VerifyConnectionStep(),
            new DockerLoginStep(),
            new DockerPullStep(),
            new DockerComposeUpStep(),
            new DockerCleanupStep(),
        ];
    }
}
