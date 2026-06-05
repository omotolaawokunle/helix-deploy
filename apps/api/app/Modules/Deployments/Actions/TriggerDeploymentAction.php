<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\BuildRunners\Services\BuildRunnerPool;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Exceptions\NoBuildRunnerAvailableException;
use App\Modules\Deployments\Jobs\RunBuildJob;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Pipelines\Actions\StartPipelineRunAction;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TriggerDeploymentAction
{
    public function __construct(
        private readonly StartPipelineRunAction $startPipelineRunAction,
        private readonly BuildRunnerPool $buildRunnerPool,
    ) {
    }

    public function execute(Site $site, User $actor, TriggerDeploymentDTO $dto): Deployment
    {
        if ($site->status !== SiteStatus::ACTIVE) {
            throw new InvalidArgumentException('Site must be active before deploying.');
        }

        $server = $site->server;
        if ($server === null || ! $server->isManaged()) {
            throw new InvalidArgumentException('Deployments require a managed server.');
        }

        if ($this->hasActiveDeployment((string) $site->getKey())) {
            throw new ConcurrentDeploymentException('A deployment is already in progress for this site.');
        }

        if ($site->pipeline_id !== null) {
            return $this->startPipelineRunAction->execute($site, $actor, $dto);
        }

        if ($site->deploy_mode === DeployMode::GIT && ($site->repository_url === null || $site->repository_url === '')) {
            throw new InvalidArgumentException('Site repository URL is required for git deployments.');
        }

        $buildStrategy = $site->build_strategy ?? BuildStrategy::ON_SERVER;
        $buildRunnerId = null;

        if ($buildStrategy === BuildStrategy::RUNNER) {
            $organization = $site->organization;
            if ($organization === null) {
                throw new InvalidArgumentException('Site organization is required for runner builds.');
            }

            $runner = $this->buildRunnerPool->acquire($site, $organization);
            if ($runner === null) {
                throw new NoBuildRunnerAvailableException();
            }

            $buildRunnerId = (string) $runner->getKey();
        }

        $deployment = Deployment::query()->create([
            'id' => (string) Str::uuid(),
            'site_id' => (string) $site->getKey(),
            'organization_id' => (string) $site->organization_id,
            'type' => DeploymentType::DEPLOY,
            'status' => DeploymentStatus::PENDING,
            'triggered_by' => (string) $actor->getKey(),
            'trigger_type' => TriggerType::MANUAL,
            'branch' => $dto->branch ?? $site->deploy_branch,
            'build_strategy' => $buildStrategy->value,
            'build_runner_id' => $buildRunnerId,
        ]);

        if ($buildStrategy === BuildStrategy::RUNNER) {
            RunBuildJob::dispatch(
                deploymentId: (string) $deployment->getKey(),
                actorId: (string) $actor->getKey(),
            );
        } else {
            RunDeploymentJob::dispatch(
                deploymentId: (string) $deployment->getKey(),
                actorId: (string) $actor->getKey(),
            );
        }

        AuditLog::record(
            operation: 'deployment.triggered',
            resource: $deployment,
            beforeState: null,
            afterState: [
                'siteId' => $site->getKey(),
                'branch' => $deployment->branch,
                'status' => DeploymentStatus::PENDING->value,
                'buildStrategy' => $buildStrategy->value,
                'buildRunnerId' => $buildRunnerId,
            ],
        );

        return $deployment;
    }

    private function hasActiveDeployment(string $siteId): bool
    {
        return Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('site_id', $siteId)
            ->whereIn('status', [
                DeploymentStatus::PENDING->value,
                DeploymentStatus::BUILDING->value,
                DeploymentStatus::BUILT->value,
                DeploymentStatus::RUNNING->value,
                DeploymentStatus::AWAITING_APPROVAL->value,
            ])
            ->exists();
    }
}
