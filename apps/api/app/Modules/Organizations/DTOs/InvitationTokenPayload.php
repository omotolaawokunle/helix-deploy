<?php

declare(strict_types=1);

namespace App\Modules\Organizations\DTOs;

use App\Modules\Teams\Enums\TeamRole;

readonly class InvitationTokenPayload
{
    public function __construct(
        public string $organizationId,
        public string $email,
        public TeamRole $role,
    ) {
    }
}
