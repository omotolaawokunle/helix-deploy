<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Servers\Actions\ListServerServiceCredentialsAction;
use App\Modules\Servers\Actions\RevealServerServiceCredentialAction;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Resources\ServerServiceCredentialResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerServiceCredentialController extends Controller
{
    public function index(
        string $server,
        ListServerServiceCredentialsAction $listServerServiceCredentialsAction,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewServiceCredentials', $serverModel);

        $organization = $serverModel->organization;
        abort_if($organization === null, 404);

        return ServerServiceCredentialResource::collection(
            $listServerServiceCredentialsAction->execute($serverModel, $organization),
        );
    }

    public function reveal(
        string $server,
        string $credential,
        Request $request,
        RevealServerServiceCredentialAction $revealServerServiceCredentialAction,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $this->authorize('revealServiceCredentials', $serverModel);

        $organization = $serverModel->organization;
        abort_if($organization === null, 404);

        $credentialModel = Credential::query()
            ->forOrganization($organization)
            ->whereKey($credential)
            ->first();

        if ($credentialModel === null) {
            throw (new ModelNotFoundException())->setModel(Credential::class, [$credential]);
        }

        try {
            $value = $revealServerServiceCredentialAction->execute($serverModel, $organization, $credentialModel);
        } catch (AuthorizationException) {
            abort(403);
        }

        return response()->json([
            'data' => [
                'id' => (string) $credentialModel->getKey(),
                'name' => $credentialModel->name,
                'value' => $value,
            ],
        ]);
    }

    private function resolveServer(string $serverId): Server
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->first();

        if ($server === null) {
            throw (new ModelNotFoundException())->setModel(Server::class, [$serverId]);
        }

        return $server;
    }
}
