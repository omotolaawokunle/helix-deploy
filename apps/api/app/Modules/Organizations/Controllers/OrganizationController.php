<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Actions\CreateOrganizationAction;
use App\Modules\Organizations\DTOs\CreateOrganizationDTO;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Requests\CreateOrganizationRequest;
use App\Modules\Organizations\Requests\UpdateOrganizationRequest;
use App\Modules\Organizations\Resources\OrganizationResource;
use App\Modules\Organizations\Services\OrganizationTableFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(
        Request $request,
        OrganizationTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $organizations = $tableFilterService->paginate(
            query: $user->organizations()->select('organizations.*'),
            request: $request,
        );

        return OrganizationResource::collection($organizations);
    }

    public function store(CreateOrganizationRequest $request, CreateOrganizationAction $action): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $organization = $action->execute($user, CreateOrganizationDTO::fromRequest($request));

        if ($user->current_organization_id === null) {
            $user->forceFill(['current_organization_id' => $organization->getKey()])->save();
        }

        return OrganizationResource::make($organization)->response()->setStatusCode(201);
    }

    public function show(Organization $org, Request $request): OrganizationResource
    {
        $this->authorize('view', $org);

        return OrganizationResource::make($org);
    }

    public function update(UpdateOrganizationRequest $request, Organization $org): OrganizationResource
    {
        $this->authorize('update', $org);

        $org->forceFill([
            'name' => (string) $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
        ])->save();

        return OrganizationResource::make($org->refresh());
    }

    public function switchOrganization(Organization $org, Request $request): JsonResponse
    {
        $this->authorize('view', $org);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $user->forceFill([
            'current_organization_id' => (string) $org->getKey(),
        ])->save();

        Auth::setUser($user);

        return response()->json(status: 204);
    }
}
