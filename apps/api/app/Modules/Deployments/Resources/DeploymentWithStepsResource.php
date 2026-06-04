<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Deployments\Models\Deployment
 */
class DeploymentWithStepsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            (new DeploymentResource($this->resource))->toArray($request),
            [
                'steps' => DeploymentStepResource::collection(
                    $this->whenLoaded('steps', $this->steps, collect()),
                ),
                'duration' => $this->duration(),
            ],
        );
    }
}
