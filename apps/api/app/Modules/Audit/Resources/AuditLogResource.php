<?php

declare(strict_types=1);

namespace App\Modules\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Audit\Models\AuditLog
 */
final class AuditLogResource extends JsonResource
{
    public static bool $includeSensitiveState = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => (string) $this->getKey(),
            'operation' => $this->operation,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null ? null : [
                'id' => (string) $this->actor->getKey(),
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ]),
            'resourceType' => $this->resource_type,
            'resourceId' => $this->resource_id,
            'ipAddress' => $this->ip_address,
            'requestId' => $this->request_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];

        if (self::$includeSensitiveState) {
            $payload['beforeState'] = $this->before_state;
            $payload['afterState'] = $this->after_state;
        }

        return $payload;
    }
}
