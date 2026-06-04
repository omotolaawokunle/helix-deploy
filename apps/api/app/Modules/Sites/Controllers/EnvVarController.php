<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Sites\Actions\EnvVarActions\CreateEnvVarAction;
use App\Modules\Sites\Actions\EnvVarActions\DeleteEnvVarAction;
use App\Modules\Sites\Actions\EnvVarActions\RevealEnvVarAction;
use App\Modules\Sites\Actions\EnvVarActions\UpdateEnvVarAction;
use App\Modules\Sites\Jobs\SyncEnvVarsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\StoreEnvVarRequest;
use App\Modules\Sites\Requests\UpdateEnvVarRequest;
use App\Modules\Sites\Resources\EnvVarResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvVarController extends Controller
{
    public function index(string $site): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('view', $siteModel);

        $org = $siteModel->organization;
        abort_if($org === null, 404);

        $credentials = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $siteModel->getMorphClass())
            ->where('credentialable_id', (string) $siteModel->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->orderBy('name')
            ->get();

        return EnvVarResource::collection($credentials);
    }

    public function store(
        string $site,
        StoreEnvVarRequest $request,
        CreateEnvVarAction $createEnvVarAction,
    ): EnvVarResource {
        $siteModel = $this->resolveSite($site);
        $this->authorize('manageEnvVars', $siteModel);

        $org = $siteModel->organization;
        abort_if($org === null, 404);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $credential = $createEnvVarAction->execute(
            site: $siteModel,
            org: $org,
            actor: $actor,
            key: (string) $request->validated('key'),
            value: (string) $request->validated('value'),
        );

        return EnvVarResource::make($credential);
    }

    public function update(
        string $site,
        string $credential,
        UpdateEnvVarRequest $request,
        UpdateEnvVarAction $updateEnvVarAction,
    ): EnvVarResource {
        $siteModel = $this->resolveSite($site);
        $this->authorize('manageEnvVars', $siteModel);

        $org = $siteModel->organization;
        abort_if($org === null, 404);

        $credentialModel = $this->resolveCredential($credential, $siteModel, $org);

        $credentialModel = $updateEnvVarAction->execute(
            site: $siteModel,
            org: $org,
            credential: $credentialModel,
            value: (string) $request->validated('value'),
        );

        return EnvVarResource::make($credentialModel);
    }

    public function destroy(
        string $site,
        string $credential,
        Request $request,
        DeleteEnvVarAction $deleteEnvVarAction,
    ): JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('manageEnvVars', $siteModel);

        $org = $siteModel->organization;
        abort_if($org === null, 404);

        $credentialModel = $this->resolveCredential($credential, $siteModel, $org);

        $deleteEnvVarAction->execute($siteModel, $org, $credentialModel);

        return response()->json(status: 204);
    }

    public function reveal(
        string $site,
        string $credential,
        Request $request,
        RevealEnvVarAction $revealEnvVarAction,
    ): JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('revealEnvVar', $siteModel);

        $org = $siteModel->organization;
        abort_if($org === null, 404);

        $credentialModel = $this->resolveCredential($credential, $siteModel, $org);

        $value = $revealEnvVarAction->execute($siteModel, $org, $credentialModel);

        return response()->json([
            'data' => [
                'id' => (string) $credentialModel->getKey(),
                'key' => $credentialModel->name,
                'value' => $value,
            ],
        ]);
    }

    public function sync(string $site, Request $request): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('syncEnvVars', $siteModel);

        SyncEnvVarsJob::dispatch((string) $siteModel->getKey());

        return response()->json([
            'message' => 'Environment variable sync has been queued.',
        ], 202);
    }

    private function resolveSite(string $siteId): Site
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            throw (new ModelNotFoundException())->setModel(Site::class, [$siteId]);
        }

        return $site;
    }

    private function resolveCredential(
        string $credentialId,
        Site $site,
        \App\Modules\Organizations\Models\Organization $org,
    ): Credential {
        $credential = Credential::query()
            ->forOrganization($org)
            ->whereKey($credentialId)
            ->first();

        if ($credential === null) {
            throw (new ModelNotFoundException())->setModel(Credential::class, [$credentialId]);
        }

        if (
            $credential->type !== CredentialType::ENV_VAR
            || (string) $credential->credentialable_id !== (string) $site->getKey()
            || $credential->credentialable_type !== $site->getMorphClass()
        ) {
            throw (new ModelNotFoundException())->setModel(Credential::class, [$credentialId]);
        }

        return $credential;
    }
}
