<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{server: \App\Modules\Servers\Models\Server, publicKey: string|null} $resource */
        $resource = $this->resource;

        return [
            'server' => ServerResource::make($resource['server']->loadMissing(['project', 'environment'])),
            'publicKey' => $resource['publicKey'],
        ];
    }
}
