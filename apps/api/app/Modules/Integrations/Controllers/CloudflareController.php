<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\CloudflareIntegration;
use App\Modules\Integrations\Requests\ConnectCloudflareRequest;
use App\Modules\Integrations\Resources\CloudflareConnectionResource;
use App\Modules\Integrations\Resources\CloudflareZoneResource;
use App\Modules\Integrations\Services\CloudflareConnectionService;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CloudflareController extends Controller
{
    public function show(Organization $org, CloudflareConnectionService $connectionService): JsonResponse
    {
        $this->authorize('viewAny', [CloudflareIntegration::class, $org]);

        $connection = $connectionService->connectionFor($org);

        if ($connection === null) {
            return response()->json([
                'data' => [
                    'connected' => false,
                    'status' => 'disconnected',
                ],
            ]);
        }

        return response()->json([
            'data' => CloudflareConnectionResource::make($connection),
        ]);
    }

    public function connect(
        Organization $org,
        ConnectCloudflareRequest $request,
        CloudflareConnectionService $connectionService,
    ): JsonResponse {
        $this->authorize('manage', [CloudflareIntegration::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $connection = $connectionService->connect(
            organization: $org,
            actor: $actor,
            token: (string) $request->validated('token'),
        );

        return response()->json([
            'data' => CloudflareConnectionResource::make($connection),
        ], 201);
    }

    public function disconnect(
        Organization $org,
        CloudflareConnectionService $connectionService,
    ): JsonResponse {
        $this->authorize('manage', [CloudflareIntegration::class, $org]);

        $connectionService->disconnect($org);

        return response()->json(status: 204);
    }

    public function zones(
        Organization $org,
        CloudflareConnectionService $connectionService,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', [CloudflareIntegration::class, $org]);

        return CloudflareZoneResource::collection($connectionService->listZones($org));
    }
}
