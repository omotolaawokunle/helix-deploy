<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Deployments\Models\Deployment
 */
class DeploymentListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deploymentRelease = $this->relationLoaded('releases')
            ? $this->releases->firstWhere('deployment_id', $this->getKey())
            : $this->releases()->where('deployment_id', $this->getKey())->first();
        $activeRelease = $this->relationLoaded('releases')
            ? $this->releases->firstWhere('is_active', true)
            : $this->releases()->where('is_active', true)->first();
        $releaseId = $deploymentRelease !== null ? (string) $deploymentRelease->getKey() : null;
        $activeReleaseId = $activeRelease !== null ? (string) $activeRelease->getKey() : null;

        return array_merge(
            (new DeploymentResource($this->resource))->toArray($request),
            [
                'duration' => $this->duration(),
                'isRollbackable' => $this->isRollbackable(),
                'triggeredBy' => $this->whenLoaded('triggeredBy', fn () => [
                    'id' => (string) $this->triggeredBy?->getKey(),
                    'name' => $this->triggeredBy?->name,
                ]),
                'activeReleaseId' => $activeReleaseId,
                'releaseId' => $releaseId,
                'isActiveRelease' => $releaseId !== null && $releaseId === $activeReleaseId,
            ],
        );
    }
}
