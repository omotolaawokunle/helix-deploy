<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\DTOs\ServerServiceCredentialDTO;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerServiceCredentialRegistry;

class ListServerServiceCredentialsAction
{
    public function __construct(
        private readonly ServerServiceCredentialRegistry $registry,
    ) {
    }

    /**
     * @return list<ServerServiceCredentialDTO>
     */
    public function execute(Server $server, Organization $organization): array
    {
        $credentials = Credential::query()
            ->forOrganization($organization)
            ->where('credentialable_type', $server->getMorphClass())
            ->where('credentialable_id', (string) $server->getKey())
            ->ofType(CredentialType::SERVER_SECRET)
            ->orderBy('name')
            ->get();

        $items = [];

        foreach ($credentials as $credential) {
            $metadata = $this->registry->metadataForName((string) $credential->name);

            if ($metadata === null) {
                continue;
            }

            $items[] = new ServerServiceCredentialDTO(
                id: (string) $credential->getKey(),
                name: (string) $credential->name,
                serviceKey: $metadata['serviceKey'],
                label: $metadata['label'],
                createdAt: $credential->created_at?->toIso8601String(),
            );
        }

        return $items;
    }
}
