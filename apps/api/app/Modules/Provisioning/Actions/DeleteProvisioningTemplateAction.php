<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use Illuminate\Validation\ValidationException;

class DeleteProvisioningTemplateAction
{
    public function execute(ProvisioningTemplate $template): void
    {
        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System provisioning templates cannot be deleted.'],
            ]);
        }

        $beforeState = [
            'name' => $template->name,
            'services' => $template->services,
        ];

        $template->delete();

        AuditLog::record(
            operation: 'provisioning_template.deleted',
            resource: $template,
            beforeState: $beforeState,
        );
    }
}
