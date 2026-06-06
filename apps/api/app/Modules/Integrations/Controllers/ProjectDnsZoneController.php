<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Integrations\Requests\AssignProjectDnsZoneRequest;
use App\Modules\Integrations\Resources\ProjectDnsZoneResource;
use App\Modules\Integrations\Services\SiteDnsProvisioner;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProjectDnsZoneController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ProjectDnsZone::class, $project]);

        $zones = ProjectDnsZone::query()
            ->where('project_id', (string) $project->getKey())
            ->where('organization_id', (string) $project->organization_id)
            ->orderBy('base_domain')
            ->get();

        return ProjectDnsZoneResource::collection($zones);
    }

    public function store(
        Project $project,
        AssignProjectDnsZoneRequest $request,
        SiteDnsProvisioner $siteDnsProvisioner,
    ): JsonResponse {
        $this->authorize('manage', [ProjectDnsZone::class, $project]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $organization = $project->organization;
        abort_if($organization === null, 404);

        $projectDnsZone = $siteDnsProvisioner->assignZone(
            org: $organization,
            actor: $actor,
            projectId: (string) $project->getKey(),
            zoneId: (string) $request->validated('zoneId'),
            baseDomain: (string) $request->validated('baseDomain'),
        );

        return response()->json([
            'data' => ProjectDnsZoneResource::make($projectDnsZone),
        ], 201);
    }

    public function destroy(
        Project $project,
        ProjectDnsZone $projectDnsZone,
        SiteDnsProvisioner $siteDnsProvisioner,
    ): JsonResponse {
        abort_unless((string) $projectDnsZone->project_id === (string) $project->getKey(), 404);

        $this->authorize('delete', $projectDnsZone);

        $siteDnsProvisioner->unassignZone($projectDnsZone);

        return response()->json(status: 204);
    }
}
