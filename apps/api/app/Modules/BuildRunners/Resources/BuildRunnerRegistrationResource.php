<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildRunnerRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{runner: \App\Modules\BuildRunners\Models\BuildRunner, publicKey: string|null} $resource */
        $resource = $this->resource;

        return [
            'runner' => BuildRunnerResource::make($resource['runner']->loadMissing(['project'])),
            'publicKey' => $resource['publicKey'],
        ];
    }
}
