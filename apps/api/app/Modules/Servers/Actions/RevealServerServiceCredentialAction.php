<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerServiceCredentialRegistry;
use Illuminate\Auth\Access\AuthorizationException;

class RevealServerServiceCredentialAction
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly ServerServiceCredentialRegistry $registry,
    ) {
    }

    public function execute(Server $server, Organization $organization, Credential $credential): string
    {
        $this->assertServerSecret($server, $organization, $credential);

        $value = $this->credentialVault->getServerSecret((string) $credential->getKey(), $organization);

        AuditLog::record(
            operation: 'server_credential.revealed',
            resource: $server,
            afterState: [
                'credential_id' => (string) $credential->getKey(),
                'name' => $credential->name,
                'server_id' => (string) $server->getKey(),
            ],
        );

        return $value;
    }

    private function assertServerSecret(Server $server, Organization $organization, Credential $credential): void
    {
        if ($credential->type !== CredentialType::SERVER_SECRET) {
            throw new AuthorizationException('Credential is not a server secret.');
        }

        if ((string) $credential->organization_id !== (string) $organization->getKey()) {
            throw new AuthorizationException('Credential access denied for this organization.');
        }

        if (
            (string) $credential->credentialable_id !== (string) $server->getKey()
            || $credential->credentialable_type !== $server->getMorphClass()
        ) {
            throw new AuthorizationException('Credential does not belong to this server.');
        }

        if ($this->registry->metadataForName((string) $credential->name) === null) {
            throw new AuthorizationException('Credential is not a recognized service secret.');
        }
    }
}
