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

class RunRollbackJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $actorId,
    ) {
        $this->onQueue('deployments');
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
            ->with(['site.server', 'rollbackTarget'])
            ->whereKey($this->deploymentId)
            ->firstOrFail();

        if ($deployment->status !== DeploymentStatus::PENDING) {
            return;
        }

        $site = $deployment->site;
        abort_if($site === null, 404, 'Rollback site not found.');

        $server = $site->server;
        abort_if($server === null, 404, 'Rollback server not found.');
        abort_if($server->credential_id === null, 422, 'Server SSH credential is required for rollback.');

        Auth::loginUsingId($this->actorId);

        $beforeState = $this->releaseAuditState($site);

        $deployment->forceFill([
            'status' => DeploymentStatus::RUNNING,
            'started_at' => now(),
        ])->save();

        $deployment = $deployment->refresh();

        $pipelineSteps = $pipelineBuilder->buildRollback($site);

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
            $ctx = DeploymentContext::forRollback($deployment, $site, $server, $connection);

            $runner->run($ctx, $pipelineSteps);

            $deployment->forceFill([
                'status' => DeploymentStatus::SUCCESS,
                'finished_at' => now(),
            ])->save();

            $deployment = $deployment->refresh();

            $activeRelease = Release::query()
                ->where('deployment_id', $deployment->rollback_target_id)
                ->where('is_active', true)
                ->first();

            AuditLog::record(
                operation: 'deployment.rollback_completed',
                resource: $deployment,
                beforeState: $beforeState,
                afterState: array_merge($this->releaseAuditState($site), [
                    'releasePath' => $deployment->release_path,
                    'duration' => $deployment->duration(),
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

            $this->markRollbackFailed($deployment, $exception->stepName, $exception->result->exitCode, $beforeState);
            event(new DeploymentCompleted($deployment->refresh()));
        } catch (Throwable $exception) {
            $this->markRollbackFailed($deployment, null, null, $beforeState, $exception->getMessage());
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

        $site = $deployment->site;
        $beforeState = $site !== null ? $this->releaseAuditState($site) : [];

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
                'output' => 'Rollback job process failed: '.$exception->getMessage(),
            ]);

        AuditLog::record(
            operation: 'deployment.rollback_failed',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: array_merge($beforeState, [
                'error' => $exception->getMessage(),
            ]),
        );

        event(new DeploymentCompleted($deployment));
    }

    /**
     * @param array<string, mixed> $beforeState
     */
    private function markRollbackFailed(
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
            operation: 'deployment.rollback_failed',
            resource: $deployment->refresh(),
            beforeState: $beforeState,
            afterState: array_filter([
                'step' => $failedStepName,
                'exitCode' => $exitCode,
                'error' => $errorMessage,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseAuditState(\App\Modules\Sites\Models\Site $site): array
    {
        $activeRelease = Release::query()
            ->where('site_id', (string) $site->getKey())
            ->where('is_active', true)
            ->first();

        return [
            'activeReleaseId' => $activeRelease?->getKey(),
            'activeReleasePath' => $activeRelease?->path,
            'activeDeploymentId' => $activeRelease?->deployment_id,
        ];
    }
}
