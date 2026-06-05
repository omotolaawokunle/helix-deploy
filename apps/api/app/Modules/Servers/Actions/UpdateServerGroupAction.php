<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\DTOs\UpdateServerGroupDTO;
use App\Modules\Servers\Models\ServerGroup;
use Illuminate\Validation\ValidationException;

class UpdateServerGroupAction
{
    public function execute(ServerGroup $group, UpdateServerGroupDTO $dto): ServerGroup
    {
        $beforeState = [
            'name' => $group->name,
            'description' => $group->description,
        ];

        if ($dto->name !== null && $dto->name !== $group->name) {
            $exists = ServerGroup::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('organization_id', (string) $group->organization_id)
                ->where('name', $dto->name)
                ->whereKeyNot((string) $group->getKey())
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ['A server group with this name already exists.'],
                ]);
            }

            $group->name = $dto->name;
        }

        if ($dto->hasDescription) {
            $group->description = $dto->description;
        }

        $group->save();

        AuditLog::record(
            operation: 'server_group.updated',
            resource: $group,
            beforeState: $beforeState,
            afterState: [
                'name' => $group->name,
                'description' => $group->description,
            ],
        );

        return $group->refresh();
    }
}
