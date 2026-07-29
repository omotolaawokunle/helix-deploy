<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Actions\SetupLaravelWorkersAction;
use App\Modules\Sites\Exceptions\LaravelWorkersAlreadyConfiguredException;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Requests\SetupLaravelWorkersRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class SiteLaravelWorkersController extends Controller
{
    public function store(
        string $site,
        SetupLaravelWorkersRequest $request,
        SetupLaravelWorkersAction $setupLaravelWorkersAction,
    ): JsonResponse {
        $siteModel = $this->resolveSite($site);
        $this->authorize('setupLaravelWorkers', $siteModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $setupLaravelWorkersAction->execute(
                site: $siteModel,
                workerType: $request->workerType(),
                actor: $actor,
            );
        } catch (LaravelWorkersAlreadyConfiguredException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Laravel worker setup has been queued.',
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
}
