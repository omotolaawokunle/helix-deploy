<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use App\Modules\Servers\DTOs\InstalledServiceDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InstalledServiceDTO
 */
class ServerServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InstalledServiceDTO $service */
        $service = $this->resource;

        return [
            'key' => $service->key,
            'label' => $service->label,
            'installed' => $service->installed,
            'status' => $service->status->value,
            'statusCheckedAt' => $service->statusCheckedAt,
            'controllable' => $service->controllable,
            'version' => $service->version,
        ];
    }
}
