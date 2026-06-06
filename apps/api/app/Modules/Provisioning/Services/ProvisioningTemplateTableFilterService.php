<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class ProvisioningTemplateTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'provisioning_templates.name',
            'provisioning_templates.description',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'provisioning_templates.name',
            'provisioning_templates.created_at',
            'provisioning_templates.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'provisioning_templates.name';
    }
}
