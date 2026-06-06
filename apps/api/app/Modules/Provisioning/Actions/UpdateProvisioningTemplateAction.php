<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Provisioning\DTOs\UpdateProvisioningTemplateDTO;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use Illuminate\Validation\ValidationException;

class UpdateProvisioningTemplateAction
{
    public function execute(ProvisioningTemplate $template, UpdateProvisioningTemplateDTO $dto): ProvisioningTemplate
    {
        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System provisioning templates cannot be modified.'],
            ]);
        }

        if ($dto->name !== null && $dto->name !== $template->name) {
            $exists = ProvisioningTemplate::query()
                ->where('organization_id', (string) $template->organization_id)
                ->where('name', $dto->name)
                ->whereKeyNot($template->getKey())
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ['A provisioning template with this name already exists.'],
                ]);
            }
        }

        $beforeState = [
            'name' => $template->name,
            'description' => $template->description,
            'services' => $template->services,
            'options' => $template->options,
        ];

        $template->fill(array_filter([
            'name' => $dto->name,
            'description' => $dto->description,
            'services' => $dto->services,
            'options' => $dto->options,
        ], static fn (mixed $value): bool => $value !== null));

        $template->save();

        AuditLog::record(
            operation: 'provisioning_template.updated',
            resource: $template,
            beforeState: $beforeState,
            afterState: [
                'name' => $template->name,
                'description' => $template->description,
                'services' => $template->services,
                'options' => $template->options,
            ],
        );

        return $template->refresh();
    }
}
