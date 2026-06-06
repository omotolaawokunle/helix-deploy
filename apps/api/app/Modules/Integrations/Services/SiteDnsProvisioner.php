<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\Contracts\SiteDnsProvisionerInterface;
use App\Modules\Integrations\DTOs\SiteDnsConfigurationDTO;
use App\Modules\Integrations\Enums\DnsProvider;
use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Integrations\Exceptions\DnsRecordAlreadyExistsException;
use App\Modules\Integrations\Exceptions\SiteDnsValidationException;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class SiteDnsProvisioner implements SiteDnsProvisionerInterface
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly CloudflareClientInterface $cloudflareClient,
        private readonly CloudflareConnectionService $cloudflareConnectionService,
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
            'dns_provider' => DnsProvider::CLOUDFLARE->value,
            'dns_record_ids' => [],
            'dns_error' => null,
        ];
    }

    public function validateForCreate(Organization $org, SiteDnsConfigurationDTO $configuration): void
    {
        if (! $configuration->autoCreateDns) {
            return;
        }

        if (! $this->cloudflareConnectionService->isConnected($org)) {
            throw new SiteDnsValidationException('Cloudflare is not connected for this organization.');
        }

        $projectDnsZone = $this->resolveProjectDnsZone($org, $configuration);
        $domain = $configuration->domain;

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

        $token = $this->credentialVault->getDnsProviderCredential(
            $org,
            DnsProvider::CLOUDFLARE->credentialName(),
        );

        try {
            $hostnames = [$domain];

            if ($isApex && $configuration->includeWwwAlias) {
                $hostnames[] = 'www.'.$projectDnsZone->base_domain;
            }

            foreach ($hostnames as $hostname) {
                if ($this->cloudflareClient->recordExists($token, $projectDnsZone->zone_id, $hostname)) {
                    throw new DnsRecordAlreadyExistsException(sprintf(
                        'A DNS record for [%s] already exists in Cloudflare.',
                        $hostname,
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
            $site->forceFill([
                'dns_status' => DnsStatus::FAILED->value,
                'dns_error' => 'Missing organization, server, or DNS zone configuration.',
            ])->save();

            return;
        }

        $projectDnsZone = $site->projectDnsZone;

        if ($projectDnsZone === null) {
            $site->forceFill([
                'dns_status' => DnsStatus::FAILED->value,
                'dns_error' => 'Project DNS zone assignment was not found.',
            ])->save();

            return;
        }

        $token = $this->credentialVault->getDnsProviderCredential(
            $organization,
            DnsProvider::CLOUDFLARE->credentialName(),
        );

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
                ],
            );
        } catch (\Throwable $exception) {
            foreach ($recordIds as $recordId) {
                try {
                    $this->cloudflareClient->deleteRecord($token, $site->dns_zone_id, $recordId);
                } catch (\Throwable) {
                }
            }

            $site->forceFill([
                'dns_status' => DnsStatus::FAILED->value,
                'dns_error' => $exception->getMessage(),
            ])->save();

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

        $token = $this->credentialVault->getDnsProviderCredential(
            $organization,
            DnsProvider::CLOUDFLARE->credentialName(),
        );

        try {
            foreach ($recordIds as $recordId) {
                if (! is_string($recordId) || $recordId === '') {
                    continue;
                }

                $this->cloudflareClient->deleteRecord($token, $site->dns_zone_id, $recordId);
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
        string $zoneId,
        string $baseDomain,
    ): ProjectDnsZone {
        if (! $this->cloudflareConnectionService->isConnected($org)) {
            throw new SiteDnsValidationException('Cloudflare is not connected for this organization.');
        }

        $zones = $this->cloudflareConnectionService->listZones($org);
        $allowed = collect($zones)->contains(
            static fn ($zone): bool => $zone->id === $zoneId && $zone->name === $baseDomain,
        );

        if (! $allowed) {
            throw new SiteDnsValidationException('The selected zone is not available for this Cloudflare token.');
        }

        $projectDnsZone = ProjectDnsZone::query()->updateOrCreate(
            [
                'project_id' => $projectId,
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
        string $token,
        string $zoneId,
        string $hostname,
        string $recordName,
        string $ipAddress,
    ): string {
        $existing = $this->cloudflareClient->findARecord($token, $zoneId, $hostname);

        if ($existing !== null) {
            if ($existing->type !== 'A' || $existing->content !== $ipAddress) {
                throw new DnsRecordAlreadyExistsException(sprintf(
                    'A DNS record for [%s] already exists in Cloudflare with a different target.',
                    $hostname,
                ));
            }

            return $existing->id;
        }

        return $this->cloudflareClient->createARecord(
            token: $token,
            zoneId: $zoneId,
            recordName: $recordName,
            ipAddress: $ipAddress,
            proxied: false,
        );
    }
}
