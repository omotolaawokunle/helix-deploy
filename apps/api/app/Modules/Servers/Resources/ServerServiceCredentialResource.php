<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use App\Modules\Servers\DTOs\ServerServiceCredentialDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServerServiceCredentialDTO
 */
class ServerServiceCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServerServiceCredentialDTO $credential */
        $credential = $this->resource;

        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'serviceKey' => $credential->serviceKey,
            'label' => $credential->label,
            'createdAt' => $credential->createdAt,
        ];
    }
}
