<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\Enums\CloudflareConnectionStatus;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Models\CloudflareConnection;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Str;

final class CloudflareConnectionService
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly CloudflareClientInterface $cloudflareClient,
    ) {
    }

    public function isConnected(Organization $organization): bool
    {
        return CloudflareConnection::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $organization->getKey())
            ->where('status', CloudflareConnectionStatus::CONNECTED->value)
            ->exists();
    }

    public function connectionFor(Organization $organization): ?CloudflareConnection
    {
        return CloudflareConnection::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $organization->getKey())
            ->first();
    }

    public function connect(Organization $organization, User $actor, string $token): CloudflareConnection
    {
        if (! $this->cloudflareClient->verifyToken($token)) {
            throw new \InvalidArgumentException('Cloudflare API token is invalid.');
        }

        $credential = $this->credentialVault->storeDnsProviderCredential(
            $organization,
            DnsProvider::CLOUDFLARE->credentialName(),
            $token,
        );

        $connection = CloudflareConnection::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $organization->getKey())
            ->first();

        if ($connection === null) {
            $connection = CloudflareConnection::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => (string) $organization->getKey(),
                'credential_id' => (string) $credential->getKey(),
                'status' => CloudflareConnectionStatus::CONNECTED->value,
                'connected_by' => (string) $actor->getKey(),
            ]);
        } else {
            $connection->forceFill([
                'credential_id' => (string) $credential->getKey(),
                'status' => CloudflareConnectionStatus::CONNECTED->value,
                'connected_by' => (string) $actor->getKey(),
            ])->save();
        }

        AuditLog::record(
            operation: 'cloudflare.connected',
            resource: $connection,
            afterState: [
                'organizationId' => (string) $organization->getKey(),
                'status' => CloudflareConnectionStatus::CONNECTED->value,
            ],
        );

        return $connection;
    }

    public function disconnect(Organization $organization): void
    {
        $connection = $this->connectionFor($organization);

        if ($connection === null) {
            return;
        }

        $beforeState = [
            'organizationId' => (string) $organization->getKey(),
            'status' => $connection->status->value,
        ];

        $this->credentialVault->deleteDnsProviderCredential(
            $organization,
            DnsProvider::CLOUDFLARE->credentialName(),
        );

        $connection->delete();

        AuditLog::record(
            operation: 'cloudflare.disconnected',
            resource: null,
            beforeState: $beforeState,
            afterState: ['organizationId' => (string) $organization->getKey()],
        );
    }

    /**
     * @return list<\App\Modules\Integrations\DTOs\CloudflareZoneDTO>
     */
    public function listZones(Organization $organization): array
    {
        $token = $this->credentialVault->getDnsProviderCredential(
            $organization,
            DnsProvider::CLOUDFLARE->credentialName(),
        );

        try {
            return $this->cloudflareClient->listZones($token);
        } finally {
            sodium_memzero($token);
        }
    }
}
