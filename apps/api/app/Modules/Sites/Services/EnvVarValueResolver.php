<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;

final class EnvVarValueResolver
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    public function isReference(Credential $credential): bool
    {
        return $credential->referenced_credential_id !== null;
    }

    public function resolve(Credential $envVarCredential, Organization $org): string
    {
        $this->assertEnvVar($envVarCredential);

        if ($envVarCredential->referenced_credential_id === null) {
            return $this->credentialVault->getSecret((string) $envVarCredential->getKey(), $org);
        }

        return $this->credentialVault->getServerSecret((string) $envVarCredential->referenced_credential_id, $org);
    }

    private function assertEnvVar(Credential $credential): void
    {
        if ($credential->type !== CredentialType::ENV_VAR) {
            throw new AuthorizationException('Credential is not an environment variable.');
        }
    }
}
