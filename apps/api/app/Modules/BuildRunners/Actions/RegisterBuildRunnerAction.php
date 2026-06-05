<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Actions;

use App\Models\Organization as VaultOrganization;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\DTOs\RegisterBuildRunnerDTO;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Jobs\VerifyBuildRunnerConnectionJob;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RegisterBuildRunnerAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    /**
     * @return array{runner: BuildRunner, publicKey: string|null}
     */
    public function execute(Organization $org, User $actor, RegisterBuildRunnerDTO $dto): array
    {
        if (! in_array($dto->authMethod, ['generate', 'import'], true)) {
            throw new InvalidArgumentException('Unsupported authentication method provided.');
        }

        $runnerId = (string) Str::uuid();
        $project = $this->resolveProject($dto->projectId, $org);
        $organization = tap(
            VaultOrganization::query()->findOrFail($org->getKey()),
            static fn (VaultOrganization $organization) => $organization->setAttribute('owner_id', (string) $actor->getKey()),
        );
        $publicKey = null;

        $runner = DB::transaction(function () use (
            $actor,
            $dto,
            $organization,
            $org,
            $project,
            $runnerId,
            &$publicKey
        ): BuildRunner {
            if ($dto->authMethod === 'generate') {
                $placeholder = new BuildRunner();
                $placeholder->setAttribute('id', $runnerId);
                $storedKeyPair = $this->credentialVault->generateSSHKeyPair($organization, $placeholder, $dto->name);
                $credentialId = $storedKeyPair->credentialId;
                $publicKey = $storedKeyPair->publicKey;
            } else {
                $privateKey = $dto->privateKey;
                if (! is_string($privateKey) || trim($privateKey) === '') {
                    throw new InvalidArgumentException('A private key is required for import authentication.');
                }

                try {
                    $credential = $this->credentialVault->storePrivateKey(
                        organization: $organization,
                        owner: tap(new BuildRunner(), static function (BuildRunner $placeholder) use ($runnerId): void {
                            $placeholder->setAttribute('id', $runnerId);
                        }),
                        name: $dto->name,
                        key: $privateKey,
                    );
                    $credentialId = (string) $credential->getKey();
                } finally {
                    sodium_memzero($privateKey);
                }
            }

            $runner = BuildRunner::query()->forceCreate([
                'id' => $runnerId,
                'organization_id' => (string) $org->getKey(),
                'name' => $dto->name,
                'ip_address' => $dto->ipAddress,
                'ssh_port' => $dto->sshPort,
                'ssh_user' => $dto->sshUser,
                'status' => BuildRunnerStatus::CONNECTING->value,
                'max_concurrent_builds' => $dto->maxConcurrentBuilds,
                'cpu_cores' => $dto->cpuCores,
                'ram_gb' => $dto->ramGb,
                'supported_runtimes' => $dto->supportedRuntimes,
                'credential_id' => $credentialId,
                'project_id' => $project?->getKey(),
                'created_by' => (string) $actor->getKey(),
            ]);

            AuditLog::record(
                operation: 'build_runner.registered',
                resource: $runner,
                beforeState: null,
                afterState: [
                    'name' => $runner->name,
                    'ipAddress' => $runner->ip_address,
                    'status' => $runner->status?->value,
                    'authMethod' => $dto->authMethod,
                ],
            );

            return $runner;
        });

        VerifyBuildRunnerConnectionJob::dispatch((string) $runner->getKey());

        return [
            'runner' => $runner,
            'publicKey' => $publicKey,
        ];
    }

    private function resolveProject(?string $projectId, Organization $org): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        $project = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($projectId)
            ->where('organization_id', (string) $org->getKey())
            ->first();

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class, [$projectId]);
        }

        return $project;
    }
}
