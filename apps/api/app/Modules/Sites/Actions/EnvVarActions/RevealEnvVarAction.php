<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\EnvVarValueResolver;
use Illuminate\Auth\Access\AuthorizationException;

class RevealEnvVarAction
{
    public function __construct(
        private readonly EnvVarValueResolver $envVarValueResolver,
    ) {
    }

    public function execute(Site $site, Organization $org, Credential $credential): string
    {
        $this->assertSiteEnvVar($site, $credential);

        if ((string) $credential->organization_id !== (string) $org->getKey()) {
            throw new AuthorizationException('Credential access denied for this organization.');
        }

        $value = $this->envVarValueResolver->resolve($credential, $org);

        AuditLog::record(
            operation: 'env_var.revealed',
            resource: $site,
            afterState: [
                'key_name' => $credential->name,
                'site_id' => (string) $site->getKey(),
                'is_reference' => $credential->referenced_credential_id !== null,
                'referenced_credential_id' => $credential->referenced_credential_id,
            ],
        );

        return $value;
    }

    private function assertSiteEnvVar(Site $site, Credential $credential): void
    {
        if ($credential->type !== \App\Modules\Credentials\Enums\CredentialType::ENV_VAR) {
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
