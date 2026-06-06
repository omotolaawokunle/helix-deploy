<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Resources;

use App\Modules\Integrations\DTOs\CloudflareZoneDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CloudflareZoneDTO */
final class CloudflareZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CloudflareZoneDTO $zone */
        $zone = $this->resource;

        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'status' => $zone->status,
        ];
    }
}
