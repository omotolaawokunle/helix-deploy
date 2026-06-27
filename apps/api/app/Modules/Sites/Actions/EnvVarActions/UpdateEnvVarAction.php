<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Sites\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateEnvVarAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    public function execute(Site $site, Organization $org, Credential $credential, string $value): Credential
    {
        $this->assertSiteEnvVar($site, $credential);

        if ($credential->referenced_credential_id !== null) {
            throw new AuthorizationException('Referenced environment variables cannot be updated directly.');
        }

        $credential = $this->credentialVault->updateSecret((string) $credential->getKey(), $org, $value);

        AuditLog::record(
            operation: 'env_var.updated',
            resource: $site,
            afterState: [
                'key_name' => $credential->name,
                'site_id' => (string) $site->getKey(),
            ],
        );

        return $credential;
    }

    private function assertSiteEnvVar(Site $site, Credential $credential): void
    {
        if ($credential->type !== CredentialType::ENV_VAR) {
            throw new AuthorizationException('Credential is not an environment variable.');
        }

        if (
            (string) $credential->credentialable_id !== (string) $site->getKey()
            || $credential->credentialable_type !== $site->getMorphClass()
        ) {
            throw new AuthorizationException('Environment variable does not belong to this site.');
        }
    }
}
