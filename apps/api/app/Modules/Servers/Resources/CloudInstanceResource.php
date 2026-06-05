<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use App\Modules\Servers\DTOs\CloudInstanceDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CloudInstanceDTO
 */
final class CloudInstanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CloudInstanceDTO $instance */
        $instance = $this->resource;

        return [
            'id' => $instance->id,
            'name' => $instance->name,
            'ipAddress' => $instance->ipAddress,
            'region' => $instance->region,
            'serverType' => $instance->serverType,
            'status' => $instance->status,
            'os' => $instance->os,
        ];
    }
}
