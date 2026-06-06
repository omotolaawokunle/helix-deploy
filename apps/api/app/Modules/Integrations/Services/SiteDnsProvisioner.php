<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Contracts\DnsZoneClientInterface;
use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Integrations\DTOs\SiteDnsConfigurationDTO;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Integrations\Events\SiteDnsSslStatusChanged;
use App\Modules\Integrations\Exceptions\DnsRecordAlreadyExistsException;
use App\Modules\Integrations\Exceptions\SiteDnsValidationException;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class SiteDnsProvisioner implements SiteDnsProvisionerInterface
{
    public function __construct(
        private readonly DnsProviderRegistry $dnsProviderRegistry,
        private readonly CloudflareHostnameResolver $hostnameResolver,
    ) {
    }

    public function resolveSiteAttributes(Organization $org, SiteDnsConfigurationDTO $configuration): array
    {
        if (! $configuration->autoCreateDns) {
            return [
                'auto_create_dns' => false,
                'is_apex' => false,
                'dns_status' => DnsStatus::NONE->value,
            ];
        }

        $projectDnsZone = $this->resolveProjectDnsZone($org, $configuration);
        $provider = $this->resolveProviderFromZone($projectDnsZone);
        $domain = $configuration->domain;
        $aliases = $configuration->aliases;

        if (! $this->hostnameResolver->belongsToZone($domain, $projectDnsZone->base_domain)) {
            throw new SiteDnsValidationException(sprintf(
                'Domain [%s] is not under the assigned zone [%s].',
                $domain,
                $projectDnsZone->base_domain,
            ));
        }

        $isApex = $this->hostnameResolver->isApex($domain, $projectDnsZone->base_domain);

        if ($isApex && $configuration->includeWwwAlias) {
            $wwwAlias = 'www.'.$projectDnsZone->base_domain;

            if (! in_array($wwwAlias, $aliases, true)) {
                $aliases[] = $wwwAlias;
            }
        }

        return [
            'auto_create_dns' => true,
            'is_apex' => $isApex,
            'aliases' => array_values($aliases),
            'project_dns_zone_id' => (string) $projectDnsZone->getKey(),
            'dns_zone_id' => $projectDnsZone->zone_id,
            'dns_status' => DnsStatus::PENDING->value,
            'dns_provider' => $provider->value,
            'dns_record_ids' => [],
            'dns_error' => null,
        ];
    }

    public function validateForCreate(Organization $org, SiteDnsConfigurationDTO $configuration): void
    {
        if (! $configuration->autoCreateDns) {
            return;
        }

        $projectDnsZone = $this->resolveProjectDnsZone($org, $configuration);
        $provider = $this->resolveProviderFromZone($projectDnsZone);
        $domain = $configuration->domain;

        if (! $this->dnsProviderRegistry->isConnected($org, $provider)) {
            throw new SiteDnsValidationException(sprintf(
                '%s is not connected for this organization.',
                $provider->label(),
            ));
        }

        if (! $this->hostnameResolver->belongsToZone($domain, $projectDnsZone->base_domain)) {
            throw new SiteDnsValidationException(sprintf(
                'Domain [%s] is not under the assigned zone [%s].',
                $domain,
                $projectDnsZone->base_domain,
            ));
        }

        $isApex = $this->hostnameResolver->isApex($domain, $projectDnsZone->base_domain);

        if ($configuration->includeWwwAlias && ! $isApex) {
            throw new SiteDnsValidationException('The www alias option is only available for apex domains.');
        }

        $token = $this->dnsProviderRegistry->getCredential($org, $provider);
        $client = $this->dnsProviderRegistry->client($provider);

        try {
            $hostnames = [$domain];

            if ($isApex && $configuration->includeWwwAlias) {
                $hostnames[] = 'www.'.$projectDnsZone->base_domain;
            }

            foreach ($hostnames as $hostname) {
                if ($client->recordExists($token, $projectDnsZone->zone_id, $hostname)) {
                    throw new DnsRecordAlreadyExistsException(sprintf(
                        'A DNS record for [%s] already exists in %s.',
                        $hostname,
                        $provider->label(),
                    ));
                }
            }
        } finally {
            sodium_memzero($token);
        }
    }

    public function provision(Site $site): void
    {
        if (! $site->auto_create_dns) {
            return;
        }

        $organization = $site->organization;
        $server = $site->server;

        if ($organization === null || $server === null || $site->dns_zone_id === null) {
            $this->markDnsFailed($site, 'Missing organization, server, or DNS zone configuration.');

            return;
        }

        $projectDnsZone = $site->projectDnsZone;

        if ($projectDnsZone === null) {
            $this->markDnsFailed($site, 'Project DNS zone assignment was not found.');

            return;
        }

        $provider = $this->resolveProviderFromSite($site);
        $token = $this->dnsProviderRegistry->getCredential($organization, $provider);
        $client = $this->dnsProviderRegistry->client($provider);
        $recordIds = [];

        try {
            $hostnames = [$site->domain];

            foreach ($site->aliases ?? [] as $alias) {
                if (is_string($alias) && str_starts_with(strtolower($alias), 'www.')) {
                    $hostnames[] = $alias;
                }
            }

            foreach (array_unique($hostnames) as $hostname) {
                $recordName = $this->hostnameResolver->recordName($hostname, $projectDnsZone->base_domain);
                $recordId = $this->resolveOrCreateARecord(
                    client: $client,
                    provider: $provider,
                    token: $token,
                    zoneId: $site->dns_zone_id,
                    hostname: $hostname,
                    recordName: $recordName,
                    ipAddress: (string) $server->ip_address,
                );
                $recordIds[] = $recordId;
            }

            $site->forceFill([
                'dns_status' => DnsStatus::ACTIVE->value,
                'dns_record_ids' => $recordIds,
                'dns_error' => null,
            ])->save();

            AuditLog::record(
                operation: 'site.dns_record.created',
                resource: $site,
                afterState: [
                    'domain' => $site->domain,
                    'recordIds' => $recordIds,
                    'zoneId' => $site->dns_zone_id,
                    'provider' => $provider->value,
                ],
            );

            event(new SiteDnsSslStatusChanged($site->refresh()));
        } catch (\Throwable $exception) {
            foreach ($recordIds as $recordId) {
                try {
                    $client->deleteRecord($token, $site->dns_zone_id, $recordId);
                } catch (\Throwable) {
                }
            }

            $this->markDnsFailed($site, $exception->getMessage());

            AuditLog::record(
                operation: 'site.dns_record.failed',
                resource: $site,
                afterState: [
                    'domain' => $site->domain,
                    'message' => $exception->getMessage(),
                ],
            );
        } finally {
            sodium_memzero($token);
        }
    }

    public function deleteRecords(Site $site): void
    {
        if (! $site->auto_create_dns || $site->dns_zone_id === null) {
            return;
        }

        /** @var list<string> $recordIds */
        $recordIds = is_array($site->dns_record_ids) ? $site->dns_record_ids : [];

        if ($recordIds === []) {
            return;
        }

        $organization = $site->organization;

        if ($organization === null) {
            return;
        }

        $provider = $this->resolveProviderFromSite($site);
        $token = $this->dnsProviderRegistry->getCredential($organization, $provider);
        $client = $this->dnsProviderRegistry->client($provider);

        try {
            foreach ($recordIds as $recordId) {
                if (! is_string($recordId) || $recordId === '') {
                    continue;
                }

                $client->deleteRecord($token, $site->dns_zone_id, $recordId);
            }

            AuditLog::record(
                operation: 'site.dns_record.deleted',
                resource: $site,
                beforeState: [
                    'domain' => $site->domain,
                    'recordIds' => $recordIds,
                ],
            );
        } finally {
            sodium_memzero($token);
        }
    }

    public function assignZone(
        Organization $org,
        User $actor,
        string $projectId,
        DnsProvider $provider,
        string $zoneId,
        string $baseDomain,
    ): ProjectDnsZone {
        $this->dnsProviderRegistry->assertZoneAvailable($org, $provider, $zoneId, $baseDomain);

        $projectDnsZone = ProjectDnsZone::query()->updateOrCreate(
            [
                'project_id' => $projectId,
                'dns_provider' => $provider->value,
                'zone_id' => $zoneId,
            ],
            [
                'organization_id' => (string) $org->getKey(),
                'base_domain' => $baseDomain,
                'assigned_by' => (string) $actor->getKey(),
            ],
        );

        AuditLog::record(
            operation: 'project.dns_zone.assigned',
            resource: $projectDnsZone,
            afterState: [
                'projectId' => $projectId,
                'provider' => $provider->value,
                'zoneId' => $zoneId,
                'baseDomain' => $baseDomain,
            ],
        );

        return $projectDnsZone;
    }

    public function unassignZone(ProjectDnsZone $projectDnsZone): void
    {
        $beforeState = [
            'projectId' => $projectDnsZone->project_id,
            'provider' => $projectDnsZone->dns_provider,
            'zoneId' => $projectDnsZone->zone_id,
            'baseDomain' => $projectDnsZone->base_domain,
        ];

        $projectDnsZone->delete();

        AuditLog::record(
            operation: 'project.dns_zone.unassigned',
            resource: null,
            beforeState: $beforeState,
        );
    }

    private function resolveProjectDnsZone(Organization $org, SiteDnsConfigurationDTO $configuration): ProjectDnsZone
    {
        if ($configuration->projectDnsZoneId === null) {
            throw new SiteDnsValidationException('A project DNS zone must be selected when auto-create DNS is enabled.');
        }

        if ($configuration->projectId === null) {
            throw new SiteDnsValidationException('A project must be selected when auto-create DNS is enabled.');
        }

        $projectDnsZone = ProjectDnsZone::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($configuration->projectDnsZoneId)
            ->where('organization_id', (string) $org->getKey())
            ->where('project_id', $configuration->projectId)
            ->first();

        if ($projectDnsZone === null) {
            throw (new ModelNotFoundException())->setModel(ProjectDnsZone::class, [$configuration->projectDnsZoneId]);
        }

        return $projectDnsZone;
    }

    private function resolveOrCreateARecord(
        DnsZoneClientInterface $client,
        DnsProvider $provider,
        string $token,
        string $zoneId,
        string $hostname,
        string $recordName,
        string $ipAddress,
    ): string {
        $existing = $client->findARecord($token, $zoneId, $hostname);

        if ($existing !== null) {
            if ($existing->type !== 'A' || $existing->content !== $ipAddress) {
                throw new DnsRecordAlreadyExistsException(sprintf(
                    'A DNS record for [%s] already exists in %s with a different target.',
                    $hostname,
                    $provider->label(),
                ));
            }

            return $existing->id;
        }

        return $client->createARecord(
            token: $token,
            zoneId: $zoneId,
            recordName: $recordName,
            ipAddress: $ipAddress,
            proxied: false,
        );
    }

    private function resolveProviderFromZone(ProjectDnsZone $projectDnsZone): DnsProvider
    {
        $provider = $projectDnsZone->dns_provider;

        if ($provider instanceof DnsProvider) {
            return $provider;
        }

        if (is_string($provider) && $provider !== '') {
            $resolved = DnsProvider::tryFrom($provider);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return DnsProvider::CLOUDFLARE;
    }

    private function resolveProviderFromSite(Site $site): DnsProvider
    {
        $provider = $site->dns_provider;

        if ($provider instanceof DnsProvider) {
            return $provider;
        }

        if (is_string($provider) && $provider !== '') {
            $resolved = DnsProvider::tryFrom($provider);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return DnsProvider::CLOUDFLARE;
    }

    private function markDnsFailed(Site $site, string $message): void
    {
        $site->forceFill([
            'dns_status' => DnsStatus::FAILED->value,
            'dns_error' => $message,
        ])->save();

        event(new SiteDnsSslStatusChanged($site->refresh()));
    }
}
