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
        $site = $this->relationLoaded('site') ? $this->site : null;
        $activeRelease = $this->releases()->where('is_active', true)->first();

        return array_merge(
            (new DeploymentResource($this->resource))->toArray($request),
            [
                'steps' => DeploymentStepResource::collection(
                    $this->whenLoaded('steps', $this->steps, collect()),
                ),
                'duration' => $this->duration(),
                'activeReleaseId' => $activeRelease !== null ? (string) $activeRelease->getKey() : null,
                'site' => $site !== null ? [
                    'id' => (string) $site->getKey(),
                    'domain' => $site->domain,
                    'deployBranch' => $site->deploy_branch,
                    'serverId' => $site->server_id,
                    'isProduction' => $site->relationLoaded('environment')
                        ? ($site->environment?->is_production ?? false)
                        : false,
                ] : null,
            ],
        );
    }
}
