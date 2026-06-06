<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\DTOs\CreateServerGroupDTO;
use App\Modules\Servers\Models\ServerGroup;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateServerGroupAction
{
    public function execute(Organization $org, CreateServerGroupDTO $dto): ServerGroup
    {
        $exists = ServerGroup::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->where('name', $dto->name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A server group with this name already exists.'],
            ]);
        }

        $group = ServerGroup::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => (string) $org->getKey(),
            'name' => $dto->name,
            'description' => $dto->description,
        ]);

        AuditLog::record(
            operation: 'server_group.created',
            resource: $group,
            afterState: [
                'name' => $group->name,
                'description' => $group->description,
            ],
        );

        return $group;
    }
}
