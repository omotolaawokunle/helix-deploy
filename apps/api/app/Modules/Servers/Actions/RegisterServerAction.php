<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Models\Organization as VaultOrganization;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\DTOs\RegisterServerDTO;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Jobs\VerifyServerConnectionJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RegisterServerAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    /**
     * @return array{server: Server, publicKey: string|null}
     */
    public function execute(Organization $org, User $actor, RegisterServerDTO $dto): array
    {
        if (! in_array($dto->authMethod, ['generate', 'import'], true)) {
            throw new InvalidArgumentException('Unsupported authentication method provided.');
        }

        $serverId = (string) Str::uuid();
        $project = $this->resolveProject($dto->projectId, $org);
        $environment = $this->resolveEnvironment($dto->environmentId, $org, $project);
        $organization = tap(
            VaultOrganization::query()->findOrFail($org->getKey()),
            static fn (VaultOrganization $organization) => $organization->setAttribute('owner_id', (string) $actor->getKey()),
        );
        $publicKey = null;

        $server = DB::transaction(function () use (
            $actor,
            $dto,
            $environment,
            $organization,
            $org,
            $project,
            $serverId,
            &$publicKey
        ): Server {
            if ($dto->authMethod === 'generate') {
                $placeholder = new Server();
                $placeholder->setAttribute('id', $serverId);
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
                        owner: tap(new Server(), static function (Server $placeholder) use ($serverId): void {
                            $placeholder->setAttribute('id', $serverId);
                        }),
                        name: $dto->name,
                        key: $privateKey,
                    );
                    $credentialId = (string) $credential->getKey();
                } finally {
                    sodium_memzero($privateKey);
                }
            }

            $server = Server::query()->forceCreate([
                'id' => $serverId,
                'organization_id' => (string) $org->getKey(),
                'project_id' => $project?->getKey(),
                'environment_id' => $environment?->getKey(),
                'hostname' => $dto->hostname,
                'ip_address' => $dto->ipAddress,
                'ssh_port' => $dto->sshPort,
                'ssh_user' => $dto->sshUser,
                'provider' => $dto->provider->value,
                'region' => $dto->region,
                'server_type' => $dto->serverType,
                'status' => ServerStatus::CONNECTING->value,
                'management_mode' => $dto->managementMode->value,
                'credential_id' => $credentialId,
                'tags' => $dto->tags,
                'created_by' => (string) $actor->getKey(),
            ]);

            AuditLog::record(
                operation: 'server.registered',
                resource: $server,
                beforeState: null,
                afterState: [
                    'hostname' => $server->hostname,
                    'ipAddress' => $server->ip_address,
                    'status' => $server->status?->value,
                    'authMethod' => $dto->authMethod,
                ],
            );

            return $server;
        });

        VerifyServerConnectionJob::dispatch((string) $server->getKey());

        return [
            'server' => $server,
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

    private function resolveEnvironment(?string $environmentId, Organization $org, ?Project $project): ?Environment
    {
        if ($environmentId === null) {
            return null;
        }

        $query = Environment::query()
            ->whereKey($environmentId)
            ->where('organization_id', (string) $org->getKey());

        if ($project !== null) {
            $query->where('project_id', (string) $project->getKey());
        }

        $environment = $query->first();

        if ($environment === null) {
            throw (new ModelNotFoundException())->setModel(Environment::class, [$environmentId]);
        }

        return $environment;
    }
}
