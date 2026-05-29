<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Actions\ChangeMemberRoleAction;
use App\Modules\Organizations\Actions\InviteMemberAction;
use App\Modules\Organizations\Actions\RemoveMemberAction;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Requests\ChangeMemberRoleRequest;
use App\Modules\Organizations\Requests\InviteMemberRequest;
use App\Modules\Organizations\Resources\OrganizationMemberResource;
use App\Modules\Organizations\Services\OrganizationMemberTableFilterService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        OrganizationMemberTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('view', $org);

        $members = $tableFilterService->paginate(
            query: $org->users()->select([
                'users.*',
                'organization_users.role as membership_role',
                'organization_users.created_at as membership_joined_at',
            ]),
            request: $request,
        );

        return OrganizationMemberResource::collection(
            $members,
        );
    }

    public function invite(
        Organization $org,
        InviteMemberRequest $request,
        InviteMemberAction $action,
    ): JsonResponse {
        $this->authorize('manageMembers', $org);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $url = $action->execute(
            organization: $org,
            actor: $user,
            email: (string) $request->input('email'),
        );

        return response()->json([
            'data' => [
                'invitationUrl' => $url,
            ],
        ], 201);
    }

    public function update(
        Organization $org,
        User $user,
        ChangeMemberRoleRequest $request,
        ChangeMemberRoleAction $action,
    ): JsonResponse {
        $this->authorize('manageMembers', $org);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute(
            organization: $org,
            actor: $actor,
            member: $user,
            newRole: TeamRole::from((string) $request->input('role')),
        );

        return response()->json(status: 204);
    }

    public function destroy(
        Organization $org,
        User $user,
        Request $request,
        RemoveMemberAction $action,
    ): JsonResponse {
        $this->authorize('manageMembers', $org);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute(
            organization: $org,
            actor: $actor,
            member: $user,
        );

        return response()->json(status: 204);
    }
}
