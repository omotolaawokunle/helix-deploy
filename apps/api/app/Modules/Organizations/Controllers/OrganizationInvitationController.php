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
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrganizationInvitationController extends Controller
{
    public function accept(
        AcceptInvitationRequest $request,
        AcceptInvitationAction $action,
        InvitationTokenService $invitationTokenService,
    ): JsonResponse {
        if (! $this->hasValidInvitationSignature($request)) {
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
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $action->execute(
            organization: $organization,
            user: $user,
            email: $payload->email,
            role: $payload->role,
            teamId: $payload->teamId,
        );

        return response()->json([
            'data' => [
                'organizationId' => (string) $organization->getKey(),
                'organizationName' => (string) $organization->name,
            ],
        ]);
    }

    /**
     * Laravel signs query params with sorted keys, but validates against the raw
     * QUERY_STRING order. SPA clients (axios) often reorder params, which would
     * otherwise fail a valid invitation signature.
     */
    private function hasValidInvitationSignature(Request $request): bool
    {
        if ($request->hasValidSignature()) {
            return true;
        }

        $query = $request->query();
        $signature = $query['signature'] ?? null;
        unset($query['signature']);
        ksort($query);

        $normalized = Arr::query($query);

        if (is_string($signature) && $signature !== '') {
            $normalized .= '&signature='.$signature;
        }

        $request->server->set('QUERY_STRING', $normalized);

        return $request->hasValidSignature();
    }
}
