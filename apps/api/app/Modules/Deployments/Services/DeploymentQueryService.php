<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services;

use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;

final class DeploymentQueryService
{
    public function __construct(
        private readonly DeploymentTableFilterService $tableFilterService,
    ) {
    }

    public function cursorPaginateForSite(Site $site, Request $request): CursorPaginator
    {
        return $this->tableFilterService->cursorPaginate(
            Deployment::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('site_id', (string) $site->getKey())
                ->with(['triggeredBy', 'releases']),
            $request,
        );
    }

    public function cursorPaginateForOrganization(Organization $org, Request $request): CursorPaginator
    {
        return $this->tableFilterService->cursorPaginate(
            Deployment::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->with(['triggeredBy', 'releases', 'site.environment']),
            $request,
        );
    }
}
