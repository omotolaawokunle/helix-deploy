<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use Illuminate\Validation\ValidationException;

class CreateEnvVarAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly ValidateLinkableServerCredentialAction $validateLinkableServerCredentialAction,
    ) {
    }

    public function execute(
        Site $site,
        Organization $org,
        User $actor,
        string $key,
        ?string $value = null,
        ?string $referencedCredentialId = null,
    ): Credential {
        $exists = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $site->getMorphClass())
            ->where('credentialable_id', (string) $site->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->where('name', $key)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => ['An environment variable with this key already exists for this site.'],
            ]);
        }

        $hasValue = $value !== null && $value !== '';
        $hasReference = $referencedCredentialId !== null && $referencedCredentialId !== '';

        if ($hasValue === $hasReference) {
            throw ValidationException::withMessages([
                'value' => ['Provide either a value or a server credential reference, not both.'],
            ]);
        }

        if ($hasReference) {
            $referencedCredential = Credential::query()
                ->forOrganization($org)
                ->whereKey($referencedCredentialId)
                ->firstOrFail();

            $this->validateLinkableServerCredentialAction->execute($site, $org, $referencedCredential);

            $credential = $this->credentialVault->storeEnvVarReference(
                $org,
                $site,
                $key,
                (string) $referencedCredential->getKey(),
            );
        } else {
            $credential = $this->credentialVault->storeSecret($org, $site, $key, (string) $value);
        }

        AuditLog::record(
            operation: 'env_var.created',
            resource: $site,
            afterState: [
                'key_name' => $key,
                'site_id' => (string) $site->getKey(),
                'credential_id' => (string) $credential->getKey(),
                'is_reference' => $credential->referenced_credential_id !== null,
                'referenced_credential_id' => $credential->referenced_credential_id,
            ],
        );

        return $credential->load('referencedCredential');
    }
}
