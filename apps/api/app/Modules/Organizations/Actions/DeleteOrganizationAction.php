<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteOrganizationAction
{
    public function execute(Organization $organization, User $actor): void
    {
        $activeMembershipCount = $actor->organizations()->count();

        if ($activeMembershipCount <= 1) {
            throw ValidationException::withMessages([
                'organization' => ['You must belong to at least one other organization before deleting this one.'],
            ]);
        }

        DB::transaction(function () use ($organization, $actor): void {
            AuditLog::record(
                operation: 'organization.deleted',
                resource: $organization,
                beforeState: [
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ],
                afterState: [
                    'deleted' => true,
                ],
            );

            $organization->delete();

            if ((string) $actor->current_organization_id === (string) $organization->getKey()) {
                $fallbackOrgId = $actor->organizations()
                    ->whereKeyNot($organization->getKey())
                    ->value('organizations.id');

                $actor->forceFill([
                    'current_organization_id' => $fallbackOrgId,
                ])->save();
            }
        });
    }
}
