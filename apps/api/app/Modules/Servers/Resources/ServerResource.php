<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Servers\Models\Server
 */
class ServerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'hostname' => $this->hostname,
            'ipAddress' => $this->ip_address,
            'sshPort' => $this->ssh_port,
            'sshUser' => $this->ssh_user,
            'provider' => $this->provider?->value,
            'region' => $this->region,
            'serverType' => $this->server_type,
            'os' => $this->os,
            'phpVersion' => $this->php_version,
            'nodeVersion' => $this->node_version,
            'status' => $this->status?->value,
            'managementMode' => $this->management_mode?->value,
            'environment' => $this->whenLoaded('environment', fn () => $this->environment === null ? null : EnvironmentResource::make($this->environment)),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : ProjectResource::make($this->project)),
            'tags' => $this->tags ?? [],
            'installedServices' => $this->installed_services ?? [],
            'healthStatus' => $this->health_status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
