<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Credentials\Models\Credential;

class CredentialPolicy
{
    public function access(User $user, Credential $credential, Organization $organization): bool
    {
        return (string) $credential->organization_id === (string) $organization->getKey();
    }

    public function delete(User $user, Credential $credential, Organization $organization): bool
    {
        return $this->access($user, $credential, $organization);
    }

    public function rotate(User $user, Credential $credential, Organization $organization): bool
    {
        return $this->access($user, $credential, $organization);
    }
}
