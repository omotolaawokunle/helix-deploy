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

class DeleteEnvVarAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    public function execute(Site $site, Organization $org, Credential $credential): void
    {
        $this->assertSiteEnvVar($site, $credential);

        $keyName = $credential->name;

        $this->credentialVault->delete((string) $credential->getKey(), $org);

        AuditLog::record(
            operation: 'env_var.deleted',
            resource: $site,
            afterState: [
                'key_name' => $keyName,
                'site_id' => (string) $site->getKey(),
            ],
        );
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
