<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Jobs;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepPhase;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use App\Modules\Deployments\Services\DeploymentCancellationService;
use App\Modules\Sites\Services\Git\AuthenticatedGitCloneUrlResolver;
use App\Packages\Artifacts\Exceptions\ArtifactCorruptedException;
use App\Packages\Artifacts\Exceptions\ArtifactTransferFailedException;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\BuildStepRunner;
use App\Packages\Execution\Exceptions\DeploymentCancelledException;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\SSH\BuildRunnerSSHManager;
use App\Packages\SSH\SSHManager;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class RunBuildJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $actorId,
    ) {
        $this->onQueue('builds');
        $this->timeout = (int) config('helixdeploy.build_timeout_minutes', 30) * 60;
    }

    public function uniqueId(): string
    {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->deploymentId)
            ->first();

        return $deployment !== null
            ? 'site_'.$deployment->site_id
            : 'deployment_'.$this->deploymentId;
    }

    public function handle(
        PipelineBuilder $pipelineBuilder,
        BuildStepRunner $buildStepRunner,
        BuildRunnerSSHManager $buildRunnerSshManager,
        SSHManager $sshManager,
        CredentialVault $credentialVault,
        AuthenticatedGitCloneUrlResolver $cloneUrlResolver,
        DeploymentCancellationService $cancellationService,
        RunnerSlotManager $slotManager,
    ): void {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with(['site.server', 'site.organization', 'buildRunner'])
            ->whereKey($this->deploymentId)
            ->firstOrFail();

        if ($deployment->status !== DeploymentStatus::PENDING || ! $deployment->isRunnerBuild()) {
            return;
        }

        $runner = $deployment->buildRunner;
        abort_if($runner === null, 422, 'Build runner is required for runner strategy deployments.');
        abort_if($runner->credential_id === null, 422, 'Build runner SSH credential is required.');

        $site = $deployment->site;
        abort_if($site === null, 404, 'Deployment site not found.');

        $server = $site->server;
        abort_if($server === null, 404, 'Deployment server not found.');
        abort_if($server->credential_id === null, 422, 'Server SSH credential is required for artifact transfer.');

        $slot = $slotManager->acquire($runner, (string) $deployment->getKey());
        if ($slot === null) {
            $this->release(30);

            return;
        }

        Auth::loginUsingId($this->actorId);

        $beforeState = $this->deploymentAuditState($deployment);

        $deployment->forceFill([
            'status' => DeploymentStatus::BUILDING,
            'started_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'deployment.build_started',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: $this->deploymentAuditState($deployment),
        );

        $plan = $pipelineBuilder->buildPlan($site, $deployment);

        foreach ($plan->buildSteps as $order => $step) {
            DeploymentStep::query()->create([
                'id' => (string) Str::uuid(),
                'deployment_id' => (string) $deployment->getKey(),
                'name' => $step->name(),
                'phase' => DeploymentStepPhase::BUILD->value,
                'status' => DeploymentStepStatus::PENDING,
                'order' => $order,
            ]);
        }

        $runnerConnection = $buildRunnerSshManager->connect($runner, $credentialVault);
        $runnerConnection->connect();

        $targetConnection = $sshManager->connect($server, $credentialVault);
        $targetConnection->connect();

        try {
            $organization = $site->organization;
            abort_if($organization === null, 404, 'Deployment organization not found.');

            $ctx = BuildContext::forDeployment(
                deployment: $deployment,
                site: $site,
                runner: $runner,
                ssh: $runnerConnection,
                repositoryCloneUrl: $cloneUrlResolver->resolve($site, $organization),
            );
            $ctx->cancellation = $cancellationService;
            $ctx->targetSsh = $targetConnection;
            $ctx->targetServer = $server;

            $buildStepRunner->run($ctx, $plan->buildSteps);

            $deployment->forceFill([
                'status' => DeploymentStatus::BUILT,
                'build_artifact_id' => $ctx->artifact !== null ? (string) $ctx->artifact->getKey() : $deployment->build_artifact_id,
            ])->save();

            AuditLog::record(
                operation: 'deployment.build_completed',
                resource: $deployment->refresh(),
                beforeState: $beforeState,
                afterState: array_merge($this->deploymentAuditState($deployment), [
                    'buildArtifactId' => $deployment->build_artifact_id,
                ]),
            );

            RunDeploymentJob::dispatch(
                deploymentId: (string) $deployment->getKey(),
                actorId: $this->actorId,
            );
        } catch (DeploymentCancelledException) {
            if (isset($ctx)) {
                $ctx->flushLog();
            }

            $this->markBuildFailed($deployment, 'Build cancelled.', $beforeState);
            event(new DeploymentCompleted($deployment->refresh()));
        } catch (DeploymentStepFailedException|ArtifactCorruptedException|ArtifactTransferFailedException $exception) {
            if (isset($ctx)) {
                $ctx->flushLog();
            }

            $this->markBuildFailed($deployment, $exception->getMessage(), $beforeState);
            event(new DeploymentCompleted($deployment->refresh()));
        } catch (Throwable $exception) {
            $this->markBuildFailed($deployment, $exception->getMessage(), $beforeState);
            event(new DeploymentCompleted($deployment->refresh()));

            throw $exception;
        } finally {
            $slotManager->release($runner, $slot);
            $cancellationService->clear((string) $deployment->getKey());
            $runnerConnection->disconnect();
            $targetConnection->disconnect();
        }
    }

    public function failed(Throwable $exception): void
    {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('buildRunner')
            ->whereKey($this->deploymentId)
            ->first();

        if ($deployment === null || ! in_array($deployment->status, [DeploymentStatus::BUILDING, DeploymentStatus::PENDING], true)) {
            return;
        }

        $runner = $deployment->buildRunner;
        if ($runner instanceof BuildRunner) {
            app(RunnerSlotManager::class)->releaseByBuildId($runner, (string) $deployment->getKey());
        }

        $beforeState = $this->deploymentAuditState($deployment);

        $deployment->forceFill([
            'status' => DeploymentStatus::FAILED,
            'finished_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'deployment.build_failed',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: array_merge($this->deploymentAuditState($deployment), [
                'error' => $exception->getMessage(),
            ]),
        );

        event(new DeploymentCompleted($deployment));
    }

    /**
     * @param array<string, mixed> $beforeState
     */
    private function markBuildFailed(Deployment $deployment, string $message, array $beforeState): void
    {
        $deployment->forceFill([
            'status' => DeploymentStatus::FAILED,
            'finished_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'deployment.build_failed',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: array_merge($this->deploymentAuditState($deployment), [
                'error' => $message,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function deploymentAuditState(Deployment $deployment): array
    {
        return [
            'deploymentId' => (string) $deployment->getKey(),
            'siteId' => $deployment->site_id,
            'status' => $deployment->status->value,
            'branch' => $deployment->branch,
            'buildStrategy' => $deployment->build_strategy?->value,
            'buildRunnerId' => $deployment->build_runner_id,
        ];
    }
}
