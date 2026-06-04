<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Jobs;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentStepStatus;
use App\Modules\Deployments\Events\DeploymentCompleted;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep;
use App\Modules\Deployments\Models\Release;
use App\Packages\Execution\Contracts\ExecutionRunnerInterface;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\SSH\SSHManager;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class RunDeploymentJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $actorId,
    ) {
        $this->onQueue('deployments');
        $this->timeout = (int) config('helixdeploy.deployment_timeout_minutes', 30) * 60;
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
        ExecutionRunnerInterface $runner,
        SSHManager $sshManager,
        CredentialVault $credentialVault,
    ): void {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with(['site.server', 'site.environment'])
            ->whereKey($this->deploymentId)
            ->firstOrFail();

        if ($deployment->status !== DeploymentStatus::PENDING) {
            return;
        }

        $site = $deployment->site;
        abort_if($site === null, 404, 'Deployment site not found.');

        $server = $site->server;
        abort_if($server === null, 404, 'Deployment server not found.');
        abort_if($server->credential_id === null, 422, 'Server SSH credential is required for deployment.');

        Auth::loginUsingId($this->actorId);

        $beforeState = $this->deploymentAuditState($deployment);

        $deployment->forceFill([
            'status' => DeploymentStatus::RUNNING,
            'started_at' => now(),
        ])->save();

        $deployment = $deployment->refresh();

        AuditLog::record(
            operation: 'deployment.started',
            resource: $deployment,
            beforeState: $beforeState,
            afterState: $this->deploymentAuditState($deployment),
        );

        $pipelineSteps = $pipelineBuilder->build($site, $deployment);

        foreach ($pipelineSteps as $order => $step) {
            DeploymentStep::query()->create([
                'id' => (string) Str::uuid(),
                'deployment_id' => (string) $deployment->getKey(),
                'name' => $step->name(),
                'status' => DeploymentStepStatus::PENDING,
                'order' => $order,
            ]);
        }

        $connection = $sshManager->connect($server, $credentialVault);
        $connection->connect();

        try {
            $ctx = DeploymentContext::forDeployment($deployment, $site, $server, $connection);

            $runner->run($ctx, $pipelineSteps);

            $deployment->forceFill([
                'status' => DeploymentStatus::SUCCESS,
                'finished_at' => now(),
                'release_path' => $ctx->releasePath,
                'commit_hash' => $deployment->commit_hash ?? $ctx->deployment->commit_hash,
                'commit_message' => $deployment->commit_message ?? $ctx->deployment->commit_message,
            ])->save();

            $deployment = $deployment->refresh();
            $activeRelease = Release::query()
                ->where('deployment_id', (string) $deployment->getKey())
                ->where('is_active', true)
                ->first();

            AuditLog::record(
                operation: 'deployment.completed',
                resource: $deployment,
                beforeState: $beforeState,
                afterState: array_merge($this->deploymentAuditState($deployment), [
                    'duration' => $deployment->duration(),
                    'releasePath' => $deployment->release_path,
                    'commitHash' => $deployment->commit_hash,
                ]),
            );

            event(new DeploymentCompleted(
                $deployment,
                $activeRelease !== null ? (string) $activeRelease->getKey() : null,
            ));
        } catch (DeploymentStepFailedException $exception) {
            if (isset($ctx)) {
                $ctx->flushLog();
            }

            $this->markDeploymentFailed(
                $deployment,
                $exception->stepName,
                $exception->result->exitCode,
                $beforeState,
            );
            event(new DeploymentCompleted($deployment->refresh()));
        } catch (Throwable $exception) {
            $this->markDeploymentFailed($deployment, null, null, $beforeState, $exception->getMessage());
            event(new DeploymentCompleted($deployment->refresh()));

            throw $exception;
        } finally {
            $connection->disconnect();
        }
    }

    public function failed(Throwable $exception): void
    {
        $deployment = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->deploymentId)
            ->first();

        if ($deployment === null || $deployment->status !== DeploymentStatus::RUNNING) {
            return;
        }

        $beforeState = $this->deploymentAuditState($deployment);

        $deployment->forceFill([
            'status' => DeploymentStatus::FAILED,
            'finished_at' => now(),
        ])->save();

        DeploymentStep::query()
            ->where('deployment_id', (string) $deployment->getKey())
            ->where('status', DeploymentStepStatus::RUNNING->value)
            ->update([
                'status' => DeploymentStepStatus::FAILED->value,
                'finished_at' => now(),
                'output' => 'Job process failed: '.$exception->getMessage(),
            ]);

        AuditLog::record(
            operation: 'deployment.failed_unexpectedly',
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
    private function markDeploymentFailed(
        Deployment $deployment,
        ?string $failedStepName,
        ?int $exitCode,
        array $beforeState,
        ?string $errorMessage = null,
    ): void {
        $deployment->forceFill([
            'status' => DeploymentStatus::FAILED,
            'finished_at' => now(),
        ])->save();

        AuditLog::record(
            operation: 'deployment.failed',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: array_merge($this->deploymentAuditState($deployment), array_filter([
                'step' => $failedStepName,
                'exitCode' => $exitCode,
                'error' => $errorMessage,
            ])),
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
            'commitHash' => $deployment->commit_hash,
        ];
    }
}
