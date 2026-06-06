<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Resources;

use App\Modules\Daemons\Models\SupervisorProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupervisorProcess
 */
class DaemonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'serverId' => $this->server_id,
            'organizationId' => $this->organization_id,
            'name' => $this->name,
            'command' => $this->command,
            'directory' => $this->directory,
            'user' => $this->user,
            'processes' => $this->processes,
            'status' => $this->status->value,
            'configPath' => $this->config_path,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
