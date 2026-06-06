<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Teams\Actions\AddTeamMemberAction;
use App\Modules\Teams\Actions\ChangeTeamMemberRoleAction;
use App\Modules\Teams\Actions\RemoveTeamMemberAction;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Requests\AddTeamMemberRequest;
use App\Modules\Teams\Requests\ChangeTeamMemberRoleRequest;
use App\Modules\Teams\Resources\TeamMemberResource;
use App\Modules\Teams\Services\TeamMemberTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index(
        string $team,
        Request $request,
        TeamMemberTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('view', $teamModel);

        $members = $tableFilterService->paginate(
            query: $teamModel->users()->select([
                'users.*',
                'team_user.role as membership_role',
                'team_user.created_at as membership_joined_at',
            ]),
            request: $request,
        );

        return TeamMemberResource::collection($members);
    }

    public function store(
        string $team,
        AddTeamMemberRequest $request,
        AddTeamMemberAction $action,
    ): JsonResponse {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('manageMembers', $teamModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $member = User::query()->findOrFail((string) $request->input('userId'));

        $action->execute(
            team: $teamModel->loadMissing('organization'),
            actor: $actor,
            member: $member,
            role: TeamRole::from((string) $request->input('role')),
        );

        return response()->json(status: 201);
    }

    public function update(
        string $team,
        User $user,
        ChangeTeamMemberRoleRequest $request,
        ChangeTeamMemberRoleAction $action,
    ): JsonResponse {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('manageMembers', $teamModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute(
            team: $teamModel,
            actor: $actor,
            member: $user,
            newRole: TeamRole::from((string) $request->input('role')),
        );

        return response()->json(status: 204);
    }

    public function destroy(
        string $team,
        User $user,
        Request $request,
        RemoveTeamMemberAction $action,
    ): JsonResponse {
        $teamModel = $this->resolveTeam($team);
        $this->authorize('manageMembers', $teamModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute(
            team: $teamModel,
            actor: $actor,
            member: $user,
        );

        return response()->json(status: 204);
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
