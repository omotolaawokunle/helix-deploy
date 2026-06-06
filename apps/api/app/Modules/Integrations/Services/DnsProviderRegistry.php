<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\Contracts\DnsZoneClientInterface;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Exceptions\SiteDnsValidationException;
use App\Modules\Integrations\Services\DigitalOcean\DigitalOceanDnsClient;
use App\Modules\Organizations\Models\Organization;
use InvalidArgumentException;

final class DnsProviderRegistry
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly CloudflareClientInterface $cloudflareClient,
        private readonly DigitalOceanDnsClient $digitalOceanDnsClient,
        private readonly CloudflareConnectionService $cloudflareConnectionService,
        private readonly DigitalOceanConnectionService $digitalOceanConnectionService,
    ) {
    }

    public function client(DnsProvider $provider): DnsZoneClientInterface
    {
        return match ($provider) {
            DnsProvider::CLOUDFLARE => $this->cloudflareClient,
            DnsProvider::DIGITALOCEAN => $this->digitalOceanDnsClient,
        };
    }

    public function isConnected(Organization $organization, DnsProvider $provider): bool
    {
        return match ($provider) {
            DnsProvider::CLOUDFLARE => $this->cloudflareConnectionService->isConnected($organization),
            DnsProvider::DIGITALOCEAN => $this->digitalOceanConnectionService->isConnected($organization),
        };
    }

    public function isAnyConnected(Organization $organization): bool
    {
        foreach (DnsProvider::cases() as $provider) {
            if ($this->isConnected($organization, $provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<CloudflareZoneDTO>
     */
    public function listZones(Organization $organization, DnsProvider $provider): array
    {
        return match ($provider) {
            DnsProvider::CLOUDFLARE => $this->cloudflareConnectionService->listZones($organization),
            DnsProvider::DIGITALOCEAN => $this->digitalOceanConnectionService->listZones($organization),
        };
    }

    public function getCredential(Organization $organization, DnsProvider $provider): string
    {
        return $this->credentialVault->getDnsProviderCredential(
            $organization,
            $provider->credentialName(),
        );
    }

    public function assertZoneAvailable(
        Organization $organization,
        DnsProvider $provider,
        string $zoneId,
        string $baseDomain,
    ): void {
        if (! $this->isConnected($organization, $provider)) {
            throw new SiteDnsValidationException(sprintf(
                '%s is not connected for this organization.',
                $provider->label(),
            ));
        }

        $zones = $this->listZones($organization, $provider);
        $allowed = collect($zones)->contains(
            static fn (CloudflareZoneDTO $zone): bool => $zone->id === $zoneId && $zone->name === $baseDomain,
        );

        if (! $allowed) {
            throw new SiteDnsValidationException(sprintf(
                'The selected zone is not available for this %s account.',
                $provider->label(),
            ));
        }
    }

    public function resolveProvider(string $value): DnsProvider
    {
        $provider = DnsProvider::tryFrom($value);

        if ($provider === null) {
            throw new InvalidArgumentException(sprintf('Unsupported DNS provider [%s].', $value));
        }

        return $provider;
    }
}
