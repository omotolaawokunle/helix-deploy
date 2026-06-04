<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Exceptions\ObserveModeServerException;
use App\Modules\Deployments\Exceptions\ProductionRollbackReasonRequiredException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Modules\Deployments\Jobs\RunRollbackJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\Release;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RollbackDeploymentAction
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {}

    public function execute(Deployment $original, User $actor, ?string $reason = null): Deployment
    {
        if (! $original->isRollbackable()) {
            throw new InvalidArgumentException('Selected deployment cannot be used as a rollback target.');
        }

        $original->loadMissing(['site.server', 'site.environment']);
        $site = $original->site;
        abort_if($site === null, 404, 'Deployment site not found.');

        $server = $site->server;
        if ($server === null || ! $server->isManaged()) {
            throw new ObserveModeServerException('Rollbacks require a managed server.');
        }

        $environment = $site->environment;
        $isProduction = $environment?->is_production ?? false;

        if ($isProduction && ($reason === null || strlen(trim($reason)) < 10)) {
            throw new ProductionRollbackReasonRequiredException(
                'A reason of at least 10 characters is required for production rollbacks.',
            );
        }

        if ($original->release_path === null || $original->release_path === '') {
            throw new InvalidArgumentException('Rollback target has no release path.');
        }

        if ($this->hasActiveDeployment((string) $site->getKey())) {
            throw new ConcurrentDeploymentException('A deployment is already in progress for this site.');
        }

        abort_if($server->credential_id === null, 422, 'Server SSH credential is required for rollback.');

        $this->assertReleaseDirectoryExists($server, $original->release_path);

        $beforeState = $this->releaseAuditState($site);

        $deployment = Deployment::query()->create([
            'id' => (string) Str::uuid(),
            'site_id' => (string) $site->getKey(),
            'organization_id' => (string) $site->organization_id,
            'type' => DeploymentType::ROLLBACK,
            'status' => DeploymentStatus::PENDING,
            'triggered_by' => (string) $actor->getKey(),
            'trigger_type' => TriggerType::MANUAL,
            'branch' => $original->branch,
            'commit_hash' => $original->commit_hash,
            'commit_message' => $original->commit_message,
            'release_path' => $original->release_path,
            'rollback_target_id' => (string) $original->getKey(),
            'rollback_reason' => $reason,
        ]);

        RunRollbackJob::dispatch(
            deploymentId: (string) $deployment->getKey(),
            actorId: (string) $actor->getKey(),
        );

        AuditLog::record(
            operation: 'deployment.rollback_triggered',
            resource: $deployment,
            beforeState: $beforeState,
            afterState: [
                'originalDeploymentId' => (string) $original->getKey(),
                'releasePath' => $original->release_path,
                'reason' => $reason,
                'isProduction' => $isProduction,
            ],
        );

        return $deployment;
    }

    private function assertReleaseDirectoryExists(\App\Modules\Servers\Models\Server $server, string $releasePath): void
    {
        $connection = $this->sshManager->connect($server, $this->credentialVault);
        $connection->connect();

        try {
            $quoted = escapeshellarg($releasePath);
            $result = $connection->run("test -d {$quoted} && echo exists || echo missing");
            $output = trim($result->stdout);

            if ($output !== 'exists') {
                throw new ReleaseNotFoundException('Release directory no longer exists on the server.');
            }
        } finally {
            $connection->disconnect();
        }
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

    private function hasActiveDeployment(string $siteId): bool
    {
        return Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('site_id', $siteId)
            ->whereIn('status', [
                DeploymentStatus::PENDING->value,
                DeploymentStatus::RUNNING->value,
            ])
            ->exists();
    }
}
