<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Actions\CreateServerGroupAction;
use App\Modules\Servers\Actions\DeleteServerGroupAction;
use App\Modules\Servers\Actions\SyncServerGroupServersAction;
use App\Modules\Servers\Actions\UpdateServerGroupAction;
use App\Modules\Servers\DTOs\CreateServerGroupDTO;
use App\Modules\Servers\DTOs\UpdateServerGroupDTO;
use App\Modules\Servers\Models\ServerGroup;
use App\Modules\Servers\Requests\StoreServerGroupRequest;
use App\Modules\Servers\Requests\SyncServerGroupServersRequest;
use App\Modules\Servers\Requests\UpdateServerGroupRequest;
use App\Modules\Servers\Resources\ServerGroupResource;
use App\Modules\Servers\Services\ServerGroupTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerGroupController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        ServerGroupTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [ServerGroup::class, $org]);

        $groups = $tableFilterService->paginate(
            query: ServerGroup::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->withCount('servers'),
            request: $request,
        );

        return ServerGroupResource::collection($groups);
    }

    public function store(
        Organization $org,
        StoreServerGroupRequest $request,
        CreateServerGroupAction $action,
    ): JsonResponse {
        $this->authorize('create', [ServerGroup::class, $org]);

        $group = $action->execute($org, CreateServerGroupDTO::fromRequest($request));

        return ServerGroupResource::make($group->loadCount('servers'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $serverGroup): ServerGroupResource
    {
        $group = $this->resolveServerGroup($serverGroup);
        $this->authorize('view', $group);

        return ServerGroupResource::make(
            $group->loadCount('servers')->load('servers'),
        );
    }

    public function update(
        string $serverGroup,
        UpdateServerGroupRequest $request,
        UpdateServerGroupAction $action,
    ): ServerGroupResource {
        $group = $this->resolveServerGroup($serverGroup);
        $this->authorize('update', $group);

        $updated = $action->execute($group, UpdateServerGroupDTO::fromRequest($request));

        return ServerGroupResource::make($updated->loadCount('servers'));
    }

    public function destroy(string $serverGroup, DeleteServerGroupAction $action): JsonResponse
    {
        $group = $this->resolveServerGroup($serverGroup);
        $this->authorize('delete', $group);

        $action->execute($group);

        return response()->json(status: 204);
    }

    public function syncServers(
        string $serverGroup,
        SyncServerGroupServersRequest $request,
        SyncServerGroupServersAction $action,
    ): ServerGroupResource {
        $group = $this->resolveServerGroup($serverGroup);
        $this->authorize('syncServers', $group);

        /** @var list<string> $serverIds */
        $serverIds = array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            $request->input('serverIds', []),
        ));

        $updated = $action->execute($group, $serverIds);

        return ServerGroupResource::make($updated->loadCount('servers'));
    }

    private function resolveServerGroup(string $serverGroupId): ServerGroup
    {
        $group = ServerGroup::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverGroupId)
            ->first();

        if ($group === null) {
            throw (new ModelNotFoundException())->setModel(ServerGroup::class, [$serverGroupId]);
        }

        return $group;
    }
}
