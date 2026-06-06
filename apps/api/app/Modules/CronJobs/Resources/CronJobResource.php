<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Resources;

use App\Modules\CronJobs\Models\CronJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CronJob
 */
class CronJobResource extends JsonResource
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
            'expression' => $this->expression,
            'command' => $this->command,
            'user' => $this->user,
            'active' => $this->active,
            'description' => app(\App\Modules\CronJobs\Services\CronService::class)->describe($this->expression),
            'lastRunAt' => $this->last_run_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
