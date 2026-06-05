<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Actions\CreateTeamAction;
use App\Modules\Teams\Actions\DeleteTeamAction;
use App\Modules\Teams\Actions\SyncTeamProjectsAction;
use App\Modules\Teams\Actions\UpdateTeamAction;
use App\Modules\Teams\DTOs\CreateTeamDTO;
use App\Modules\Teams\DTOs\UpdateTeamDTO;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Requests\StoreTeamRequest;
use App\Modules\Teams\Requests\SyncTeamProjectsRequest;
use App\Modules\Teams\Requests\UpdateTeamRequest;
use App\Modules\Teams\Resources\TeamResource;
use App\Modules\Teams\Services\TeamTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        TeamTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [Team::class, $org]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $query = Team::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->withCount('users')
            ->with('projects');

        $orgRole = $user->roleInOrganization($org);

        if (! in_array($orgRole, [TeamRole::OWNER, TeamRole::ADMIN], true)) {
            $query->whereHas('users', function ($builder) use ($user): void {
                $builder->whereKey($user->getKey());
            });
        }

        $teams = $tableFilterService->paginate(
            query: $query,
            request: $request,
        );

        return TeamResource::collection($teams);
    }

    public function store(
        Organization $org,
        StoreTeamRequest $request,
        CreateTeamAction $action,
    ): JsonResponse {
        $this->authorize('create', [Team::class, $org]);

        $team = $action->execute($org, CreateTeamDTO::fromRequest($request));

        return TeamResource::make($team->loadCount('users')->load('projects'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $team): TeamResource
    {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('view', $teamModel);

        return TeamResource::make(
            $teamModel->loadCount('users')->load('projects'),
        );
    }

    public function update(
        string $team,
        UpdateTeamRequest $request,
        UpdateTeamAction $action,
    ): TeamResource {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('update', $teamModel);

        $updated = $action->execute($teamModel, UpdateTeamDTO::fromRequest($request));

        return TeamResource::make($updated->loadCount('users')->load('projects'));
    }

    public function destroy(string $team, DeleteTeamAction $action): JsonResponse
    {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('delete', $teamModel);

        $action->execute($teamModel);

        return response()->json(status: 204);
    }

    public function syncProjects(
        string $team,
        SyncTeamProjectsRequest $request,
        SyncTeamProjectsAction $action,
    ): TeamResource {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('syncProjects', $teamModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        /** @var list<string> $projectIds */
        $projectIds = array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            $request->input('projectIds', []),
        ));

        $updated = $action->execute($teamModel, $actor, $projectIds);

        return TeamResource::make($updated->loadCount('users'));
    }

    private function resolveTeam(string $teamId): Team
    {
        $team = Team::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($teamId)
            ->first();

        if ($team === null) {
            throw (new ModelNotFoundException())->setModel(Team::class, [$teamId]);
        }

        return $team;
    }
}
