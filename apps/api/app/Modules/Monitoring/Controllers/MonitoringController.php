<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Resources\ServerResource;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function servers(Organization $org, Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Server::class, $org]);

        $servers = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->orderBy('hostname')
            ->paginate(perPage: min(100, max(1, (int) $request->integer('per_page', 100))));

        return ServerResource::collection($servers);
    }
}
