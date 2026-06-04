<?php

declare(strict_types=1);

namespace App\Modules\Sites\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Actions\UpdateNginxConfigAction;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Resources\NginxConfigResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class NginxConfigController extends Controller
{
    public function show(string $site, NginxConfigGeneratorInterface $generator): NginxConfigResource
    {
        $siteModel = $this->resolveSite($site);
        $this->authorize('view', $siteModel);

        return NginxConfigResource::make([
            'siteId' => (string) $siteModel->getKey(),
            'domain' => $siteModel->domain,
            'config' => $generator->generate($siteModel),
        ]);
    }

    public function update(
        string $site,
        Request $request,
        UpdateNginxConfigAction $updateNginxConfigAction,
    ): NginxConfigResource {
        $siteModel = $this->resolveSite($site);
        $this->authorize('updateNginxConfig', $siteModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $siteModel = $updateNginxConfigAction->execute($siteModel, $actor);

        return NginxConfigResource::make([
            'siteId' => (string) $siteModel->getKey(),
            'domain' => $siteModel->domain,
            'config' => app(NginxConfigGeneratorInterface::class)->generate($siteModel),
        ]);
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
