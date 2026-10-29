<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NginxConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{siteId: string, domain: string, config: string, status?: string, message?: string|null, updatedAt?: string|null} $payload */
        $payload = $this->resource;

        return [
            'siteId' => $payload['siteId'],
            'domain' => $payload['domain'],
            'status' => $payload['status'] ?? 'ready',
            'config' => $payload['config'],
            'message' => $payload['message'] ?? null,
            'updatedAt' => $payload['updatedAt'] ?? null,
        ];
    }
}
