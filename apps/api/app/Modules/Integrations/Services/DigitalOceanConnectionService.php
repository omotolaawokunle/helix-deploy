<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Contracts\DnsZoneClientInterface;
use App\Modules\Integrations\Enums\CloudflareConnectionStatus;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Models\DigitalOceanDnsConnection;
use App\Modules\Integrations\Services\DigitalOcean\DigitalOceanDnsClient;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Str;

final class DigitalOceanConnectionService
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly DigitalOceanDnsClient $digitalOceanDnsClient,
    ) {
    }

    public function isConnected(Organization $organization): bool
    {
        return DigitalOceanDnsConnection::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $organization->getKey())
            ->where('status', CloudflareConnectionStatus::CONNECTED->value)
            ->exists();
    }

    public function connectionFor(Organization $organization): ?DigitalOceanDnsConnection
    {
        return DigitalOceanDnsConnection::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $organization->getKey())
            ->first();
    }

    public function connect(Organization $organization, User $actor, string $token): DigitalOceanDnsConnection
    {
        if (! $this->digitalOceanDnsClient->verifyToken($token)) {
            throw new \InvalidArgumentException('DigitalOcean API token is invalid.');
        }

        $credential = $this->credentialVault->storeDnsProviderCredential(
            $organization,
            DnsProvider::DIGITALOCEAN->credentialName(),
            $token,
        );

        $connection = $this->connectionFor($organization);

        if ($connection === null) {
            $connection = DigitalOceanDnsConnection::query()->create([
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
            operation: 'digitalocean_dns.connected',
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
            DnsProvider::DIGITALOCEAN->credentialName(),
        );

        $connection->delete();

        AuditLog::record(
            operation: 'digitalocean_dns.disconnected',
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
            DnsProvider::DIGITALOCEAN->credentialName(),
        );

        try {
            return $this->digitalOceanDnsClient->listZones($token);
        } finally {
            sodium_memzero($token);
        }
    }
}
