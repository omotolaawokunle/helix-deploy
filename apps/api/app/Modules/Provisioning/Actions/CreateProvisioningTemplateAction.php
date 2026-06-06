<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Provisioning\DTOs\CreateProvisioningTemplateDTO;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProvisioningTemplateAction
{
    public function execute(Organization $org, User $actor, CreateProvisioningTemplateDTO $dto): ProvisioningTemplate
    {
        $exists = ProvisioningTemplate::query()
            ->where('organization_id', (string) $org->getKey())
            ->where('name', $dto->name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A provisioning template with this name already exists.'],
            ]);
        }

        $template = ProvisioningTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => (string) $org->getKey(),
            'name' => $dto->name,
            'description' => $dto->description,
            'services' => $dto->services,
            'options' => $dto->options,
            'is_system' => false,
            'created_by' => (string) $actor->getKey(),
        ]);

        AuditLog::record(
            operation: 'provisioning_template.created',
            resource: $template,
            afterState: [
                'name' => $template->name,
                'services' => $template->services,
            ],
        );

        return $template;
    }
}
