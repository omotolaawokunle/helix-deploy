<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\DigitalOceanIntegration;
use App\Modules\Integrations\Requests\ConnectDigitalOceanRequest;
use App\Modules\Integrations\Resources\CloudflareZoneResource;
use App\Modules\Integrations\Resources\DigitalOceanConnectionResource;
use App\Modules\Integrations\Services\DigitalOceanConnectionService;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DigitalOceanController extends Controller
{
    public function show(Organization $org, DigitalOceanConnectionService $connectionService): JsonResponse
    {
        $this->authorize('viewAny', [DigitalOceanIntegration::class, $org]);

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
            'data' => DigitalOceanConnectionResource::make($connection),
        ]);
    }

    public function connect(
        Organization $org,
        ConnectDigitalOceanRequest $request,
        DigitalOceanConnectionService $connectionService,
    ): JsonResponse {
        $this->authorize('manage', [DigitalOceanIntegration::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $connection = $connectionService->connect(
            organization: $org,
            actor: $actor,
            token: (string) $request->validated('token'),
        );

        return response()->json([
            'data' => DigitalOceanConnectionResource::make($connection),
        ], 201);
    }

    public function disconnect(
        Organization $org,
        DigitalOceanConnectionService $connectionService,
    ): JsonResponse {
        $this->authorize('manage', [DigitalOceanIntegration::class, $org]);

        $connectionService->disconnect($org);

        return response()->json(status: 204);
    }

    public function zones(
        Organization $org,
        DigitalOceanConnectionService $connectionService,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', [DigitalOceanIntegration::class, $org]);

        return CloudflareZoneResource::collection($connectionService->listZones($org));
    }
}
