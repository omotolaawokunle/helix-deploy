<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Integrations\Models\DigitalOceanDnsConnection
 */
final class DigitalOceanConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'connected' => true,
            'status' => $this->status->value,
            'connectedAt' => $this->created_at?->toIso8601String(),
            'connectedBy' => $this->connected_by,
        ];
    }
}
