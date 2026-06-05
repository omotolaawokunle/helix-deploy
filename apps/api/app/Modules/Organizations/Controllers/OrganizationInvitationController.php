<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Actions\AcceptInvitationAction;
use App\Modules\Organizations\Exceptions\InvalidInvitationTokenException;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Requests\AcceptInvitationRequest;
use App\Modules\Organizations\Services\InvitationTokenService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class OrganizationInvitationController extends Controller
{
    public function accept(
        AcceptInvitationRequest $request,
        AcceptInvitationAction $action,
        InvitationTokenService $invitationTokenService,
    ): JsonResponse {
        if (! $request->hasValidSignature()) {
            if ($request->has('expires') && now()->getTimestamp() > (int) $request->query('expires')) {
                abort(410, 'Invitation link has expired.');
            }

            abort(403, 'Invalid invitation link.');
        }

        try {
            $payload = $invitationTokenService->decode((string) $request->query('token'));
        } catch (InvalidInvitationTokenException $exception) {
            abort(403, $exception->getMessage());
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $organization = Organization::query()
            ->whereKey($payload->organizationId)
            ->first();

        if ($organization === null) {
            throw (new ModelNotFoundException())->setModel(Organization::class);
        }

        $action->execute(
            organization: $organization,
            user: $user,
            email: $payload->email,
            role: $payload->role,
        );

        return response()->json([
            'data' => [
                'organizationId' => (string) $organization->getKey(),
                'organizationName' => (string) $organization->name,
            ],
        ]);
    }
}
