<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Actions\DeleteSiteAction;
use App\Modules\Sites\Events\SiteProvisioningStarted;
use App\Modules\Sites\Jobs\CreateSiteJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\StoreSiteRequest;
use App\Modules\Sites\Requests\UpdateSiteRequest;
use App\Modules\Sites\Resources\SiteResource;
use App\Modules\Sites\Services\SiteTableFilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        SiteTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [Site::class, $org]);

        $sites = $tableFilterService->paginate(
            query: Site::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey()),
            request: $request,
        );

        return SiteResource::collection($sites);
    }

    public function indexForServer(
        string $server,
        Request $request,
        SiteTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $serverModel = $this->resolveServer($server);
        $this->authorize('view', $serverModel);

        $sites = $tableFilterService->paginate(
            query: Site::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('server_id', (string) $serverModel->getKey())
                ->where('organization_id', (string) $serverModel->organization_id),
            request: $request,
        );

        return SiteResource::collection($sites);
    }

    public function store(
        string $server,
        StoreSiteRequest $request,
        CreateSiteAction $createSiteAction,
    ): \Illuminate\Http\JsonResponse {
        $serverModel = $this->resolveServer($server);
        $org = $serverModel->organization;
        abort_if($org === null, 404);

        $this->authorize('create', [Site::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $site = $createSiteAction->execute(
            server: $serverModel,
            org: $org,
            actor: $actor,
            dto: $request->toDto(),
        );

        CreateSiteJob::dispatch(
            siteId: (string) $site->getKey(),
            actorId: (string) $actor->getKey(),
        );

        event(new SiteProvisioningStarted($site));

        return SiteResource::make($site)
            ->additional([
                'channel' => 'server.'.$serverModel->getKey().'.sites',
            ])
            ->response()
            ->setStatusCode(202);
    }

    public function show(string $site): SiteResource
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('view', $siteModel);

        return SiteResource::make($siteModel);
    }

    public function update(string $site, UpdateSiteRequest $request): SiteResource
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('update', $siteModel);

        $validated = $request->validated();

        if (array_key_exists('deployBranch', $validated)) {
            $siteModel->deploy_branch = (string) $validated['deployBranch'];
        }

        if (array_key_exists('deployScript', $validated)) {
            $siteModel->deploy_script = $validated['deployScript'];
        }

        if (array_key_exists('runMigrations', $validated)) {
            $siteModel->run_migrations = (bool) $validated['runMigrations'];
        }

        if (array_key_exists('dockerImage', $validated)) {
            $siteModel->docker_image = $validated['dockerImage'];
        }

        if (array_key_exists('dockerRegistry', $validated)) {
            $siteModel->docker_registry = $validated['dockerRegistry'];
        }

        if (array_key_exists('dockerComposePath', $validated)) {
            $siteModel->docker_compose_path = $validated['dockerComposePath'];
        }

        $siteModel->save();

        return SiteResource::make($siteModel->refresh());
    }

    public function destroy(string $site, Request $request, DeleteSiteAction $deleteSiteAction): JsonResponse
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('delete', $siteModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $deleteSiteAction->execute($siteModel, $actor);

        return response()->json(status: 204);
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
}
