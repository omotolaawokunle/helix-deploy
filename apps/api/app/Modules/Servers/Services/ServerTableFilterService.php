<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Illuminate\Database\Eloquent\Builder;

class ServerTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'servers.hostname',
            'servers.ip_address',
            'servers.ssh_user',
            'servers.region',
            'servers.server_type',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'status' => 'servers.status',
            'provider' => 'servers.provider',
            'management_mode' => 'servers.management_mode',
            'project_id' => 'servers.project_id',
            'environment_id' => 'servers.environment_id',
            'tags' => function (Builder $query, mixed $value): void {
                $tags = is_array($value)
                    ? $value
                    : array_map('trim', explode(',', (string) $value));

                foreach (array_filter($tags) as $tag) {
                    $query->whereJsonContains('servers.tags', $tag);
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'servers.hostname',
            'servers.ip_address',
            'servers.status',
            'servers.provider',
            'servers.created_at',
            'servers.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-servers.created_at';
    }
}
