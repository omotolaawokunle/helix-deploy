<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Resources;

use App\Modules\Integrations\Enums\DnsProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Integrations\Models\ProjectDnsZone
 */
final class ProjectDnsZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'projectId' => $this->project_id,
            'dnsProvider' => $this->dns_provider instanceof DnsProvider
                ? $this->dns_provider->value
                : $this->dns_provider,
            'zoneId' => $this->zone_id,
            'baseDomain' => $this->base_domain,
            'assignedBy' => $this->assigned_by,
            'assignedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
