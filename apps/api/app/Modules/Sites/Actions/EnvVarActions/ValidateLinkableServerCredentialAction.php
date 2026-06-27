<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerServiceCredentialRegistry;
use App\Modules\Sites\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ValidateLinkableServerCredentialAction
{
    public function __construct(
        private readonly ServerServiceCredentialRegistry $registry,
    ) {
    }

    public function execute(Site $site, Organization $org, Credential $referencedCredential): void
    {
        if ((string) $referencedCredential->organization_id !== (string) $org->getKey()) {
            throw new AuthorizationException('Credential access denied for this organization.');
        }

        if ($referencedCredential->type !== CredentialType::SERVER_SECRET) {
            throw ValidationException::withMessages([
                'referencedCredentialId' => ['The selected credential is not a linkable server secret.'],
            ]);
        }

        $server = $site->server;

        if ($server === null) {
            throw ValidationException::withMessages([
                'referencedCredentialId' => ['This site is not attached to a server.'],
            ]);
        }

        if (
            (string) $referencedCredential->credentialable_id !== (string) $server->getKey()
            || $referencedCredential->credentialable_type !== $server->getMorphClass()
        ) {
            throw ValidationException::withMessages([
                'referencedCredentialId' => ['The credential does not belong to this site\'s server.'],
            ]);
        }

        if ($this->registry->metadataForName((string) $referencedCredential->name) === null) {
            throw ValidationException::withMessages([
                'referencedCredentialId' => ['The credential is not a recognized service secret.'],
            ]);
        }
    }

    public function assertServerOwnsCredential(Server $server, Organization $org, Credential $referencedCredential): void
    {
        if ((string) $referencedCredential->organization_id !== (string) $org->getKey()) {
            throw new AuthorizationException('Credential access denied for this organization.');
        }

        if (
            $referencedCredential->type !== CredentialType::SERVER_SECRET
            || (string) $referencedCredential->credentialable_id !== (string) $server->getKey()
            || $referencedCredential->credentialable_type !== $server->getMorphClass()
        ) {
            throw new AuthorizationException('Credential does not belong to this server.');
        }
    }
}
