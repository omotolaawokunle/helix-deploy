<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Enums\CloudProvider;
use App\Modules\Servers\Models\CloudProviderIntegration;
use App\Modules\Servers\Requests\StoreCloudProviderCredentialRequest;
use App\Modules\Servers\Resources\CloudInstanceResource;
use App\Modules\Servers\Services\Cloud\CloudProviderService;
use Illuminate\Http\JsonResponse;

final class CloudProviderController extends Controller
{
    public function index(Organization $org, CloudProviderService $cloudProviderService): JsonResponse
    {
        $this->authorize('viewAny', [CloudProviderIntegration::class, $org]);

        return response()->json([
            'data' => $cloudProviderService->listConfiguredProviders($org),
        ]);
    }

    public function store(
        Organization $org,
        StoreCloudProviderCredentialRequest $request,
        CloudProviderService $cloudProviderService,
    ): JsonResponse {
        $this->authorize('manage', [CloudProviderIntegration::class, $org]);

        $provider = $request->provider();

        if ($provider === CloudProvider::AWS) {
            $cloudProviderService->storeAwsCredential(
                $org,
                (string) $request->validated('accessKeyId'),
                (string) $request->validated('secretAccessKey'),
                (string) $request->validated('region'),
            );
        } else {
            $cloudProviderService->storeTokenCredential(
                $org,
                $provider,
                (string) $request->validated('token'),
            );
        }

        return response()->json([
            'data' => [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'configured' => true,
            ],
        ], 201);
    }

    public function destroy(
        Organization $org,
        string $provider,
        CloudProviderService $cloudProviderService,
    ): JsonResponse {
        $this->authorize('manage', [CloudProviderIntegration::class, $org]);

        $cloudProviderService->revokeCredential($org, CloudProvider::from($provider));

        return response()->json(status: 204);
    }

    public function instances(
        Organization $org,
        string $provider,
        CloudProviderService $cloudProviderService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [CloudProviderIntegration::class, $org]);

        $instances = $cloudProviderService->listInstances($org, CloudProvider::from($provider));

        return CloudInstanceResource::collection($instances);
    }
}
