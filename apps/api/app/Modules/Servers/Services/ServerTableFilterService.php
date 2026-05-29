<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

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
