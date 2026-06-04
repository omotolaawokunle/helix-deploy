<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Models\Command;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AuditLogQueryService
{

    public function cursorPaginateForOrganization(Organization $org, Request $request): CursorPaginator
    {
        return $this->applyFilters(
            AuditLog::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->with('actor'),
            $request,
        )->cursorPaginate(
            perPage: $this->resolvePerPage($request),
            cursorName: 'cursor',
        );
    }

    public function cursorPaginateForServer(Server $server, Request $request): CursorPaginator
    {
        $deploymentIds = Deployment::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereIn('site_id', Site::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('server_id', (string) $server->getKey())
                ->select('id'))
            ->pluck('id')
            ->all();

        $commandIds = Command::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->pluck('id')
            ->all();

        return $this->applyFilters(
            AuditLog::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $server->organization_id)
                ->where(function (Builder $query) use ($server, $deploymentIds, $commandIds): void {
                    $query->where(function (Builder $builder) use ($server): void {
                        $builder
                            ->where('resource_type', Server::class)
                            ->where('resource_id', (string) $server->getKey());
                    });

                    if ($deploymentIds !== []) {
                        $query->orWhere(function (Builder $builder) use ($deploymentIds): void {
                            $builder
                                ->where('resource_type', Deployment::class)
                                ->whereIn('resource_id', $deploymentIds);
                        });
                    }

                    if ($commandIds !== []) {
                        $query->orWhere(function (Builder $builder) use ($commandIds): void {
                            $builder
                                ->where('resource_type', Command::class)
                                ->whereIn('resource_id', $commandIds);
                        });
                    }
                })
                ->with('actor'),
            $request,
        )->cursorPaginate(
            perPage: $this->resolvePerPage($request),
            cursorName: 'cursor',
        );
    }

    public function countForExport(Organization $org, Request $request): int
    {
        return (int) $this->applyFilters(
            AuditLog::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey()),
            $request,
        )->count();
    }

    public function shouldQueueExport(Organization $org, Request $request): bool
    {
        return $this->countForExport($org, $request) > (int) config('audit.export.queue_threshold', 10_000);
    }

    /**
     * @return Builder<AuditLog>
     */
    public function exportQuery(Organization $org, Request $request): Builder
    {
        return $this->applyFilters(
            AuditLog::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $org->getKey())
                ->with('actor')
                ->orderBy('created_at'),
            $request,
        );
    }

    /**
     * @return Builder<AuditLog>
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($operation = $request->query('operation')) {
            $query->where('operation', (string) $operation);
        }

        if ($actorId = $request->query('actor_id')) {
            $query->where('actor_id', (string) $actorId);
        }

        if ($resourceType = $request->query('resource_type')) {
            $query->where('resource_type', (string) $resourceType);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 25);

        if ($perPage <= 0) {
            return 25;
        }

        return min($perPage, 100);
    }
}
