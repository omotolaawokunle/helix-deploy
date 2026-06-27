<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use Illuminate\Auth\Access\AuthorizationException;

final class ServerDatabaseConnectionResolver
{
    private const DEPLOY_USERNAME = 'deploy';

    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly ServerServiceCredentialRegistry $credentialRegistry,
    ) {
    }

    public function resolve(Server $server, Organization $org, DatabaseEngine $engine): DatabaseConnectionConfig
    {
        $credential = $this->findDeployCredential($server, $org, $engine);
        $password = $this->credentialVault->getServerSecret((string) $credential->getKey(), $org);

        try {
            return new DatabaseConnectionConfig(
                engine: $engine,
                host: '127.0.0.1',
                port: $engine === DatabaseEngine::POSTGRESQL ? 5432 : 3306,
                username: self::DEPLOY_USERNAME,
                password: $password,
            );
        } finally {
            sodium_memzero($password);
        }
    }

    private function findDeployCredential(Server $server, Organization $org, DatabaseEngine $engine): Credential
    {
        $suffix = match ($engine) {
            DatabaseEngine::POSTGRESQL => '-postgresql-deploy-password',
            DatabaseEngine::MYSQL => '-mysql-deploy-password',
        };

        $credential = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $server->getMorphClass())
            ->where('credentialable_id', (string) $server->getKey())
            ->ofType(CredentialType::SERVER_SECRET)
            ->where('name', 'like', '%'.$suffix)
            ->first();

        if ($credential === null) {
            throw new AuthorizationException('Deploy credentials are not available for this engine.');
        }

        if ($this->credentialRegistry->metadataForName((string) $credential->name) === null) {
            throw new AuthorizationException('Deploy credentials are not available for this engine.');
        }

        return $credential;
    }
}
