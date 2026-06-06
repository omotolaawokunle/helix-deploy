<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Models\GitProviderIntegration;
use App\Modules\Sites\Requests\StoreGitProviderTokenRequest;
use App\Modules\Sites\Resources\GitBranchResource;
use App\Modules\Sites\Resources\GitRepositoryResource;
use App\Modules\Sites\Services\GitProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitProviderController extends Controller
{
    public function index(Organization $org): JsonResponse
    {
        $this->authorize('viewAny', [GitProviderIntegration::class, $org]);

        $providers = app(GitProviderService::class)->listConfiguredProviders($org);

        return response()->json(['data' => $providers]);
    }

    public function store(
        Organization $org,
        StoreGitProviderTokenRequest $request,
        GitProviderService $gitProviderService,
    ): JsonResponse {
        $this->authorize('manage', [GitProviderIntegration::class, $org]);

        $provider = GitProvider::from((string) $request->input('provider'));

        $gitProviderService->storeProviderToken(
            $org,
            $provider,
            (string) $request->input('token'),
        );

        return response()->json([
            'data' => [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'configured' => true,
            ],
        ], 201);
    }

    public function destroy(Organization $org, string $provider, GitProviderService $gitProviderService): JsonResponse
    {
        $this->authorize('manage', [GitProviderIntegration::class, $org]);

        $resolved = GitProvider::from($provider);
        $gitProviderService->revokeProviderToken($org, $resolved);

        return response()->json(status: 204);
    }

    public function repositories(
        Organization $org,
        string $provider,
        GitProviderService $gitProviderService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [GitProviderIntegration::class, $org]);

        $resolved = GitProvider::from($provider);
        $repositories = $gitProviderService->listRepositories($org, $resolved);

        return GitRepositoryResource::collection($repositories);
    }

    public function branches(
        Organization $org,
        string $provider,
        string $owner,
        string $repo,
        GitProviderService $gitProviderService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [GitProviderIntegration::class, $org]);

        $resolved = GitProvider::from($provider);
        $branches = $gitProviderService->listBranches($org, $resolved, $owner, $repo);

        return GitBranchResource::collection($branches);
    }
}
