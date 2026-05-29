<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\DTOs\CreateOrganizationDTO;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganizationAction
{
    public function execute(User $user, CreateOrganizationDTO $dto): Organization
    {
        return DB::transaction(function () use ($user, $dto): Organization {
            $organization = Organization::query()->create([
                'name' => $dto->name,
                'slug' => $this->generateUniqueSlug($dto->name),
                'master_key_encrypted' => '{}',
                'settings' => [],
            ]);

            $organization->generateAndStoreMasterKey();

            $organization->users()->attach($user->getKey(), [
                'role' => TeamRole::OWNER->value,
            ]);

            AuditLog::record(
                operation: 'organization.created',
                resource: $organization,
                metadata: [
                    'organization_id' => (string) $organization->getKey(),
                ],
                afterState: [
                    'organization_id' => (string) $organization->getKey(),
                ],
            );

            return $organization;
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
