<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Policies;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use App\Modules\Teams\Enums\TeamRole;

class ProvisioningTemplatePolicy
{
    public function viewAny(User $user, Organization $org): bool
    {
        return $this->roleInOrganization($user, $org) !== null;
    }

    public function view(User $user, ProvisioningTemplate $template): bool
    {
        if ($template->is_system) {
            return $this->roleInOrganization($user, $user->currentOrganization()) !== null;
        }

        return $this->roleInOrganization($user, $template->organization) !== null;
    }

    public function create(User $user, Organization $org): bool
    {
        return in_array($this->roleInOrganization($user, $org), [TeamRole::OWNER, TeamRole::ADMIN], true);
    }

    public function update(User $user, ProvisioningTemplate $template): bool
    {
        if ($template->is_system) {
            return false;
        }

        return in_array(
            $this->roleInOrganization($user, $template->organization),
            [TeamRole::OWNER, TeamRole::ADMIN],
            true,
        );
    }

    public function delete(User $user, ProvisioningTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    private function roleInOrganization(User $user, ?Organization $org): ?TeamRole
    {
        if ($org === null) {
            return null;
        }

        return $user->roleInOrganization($org);
    }
}
