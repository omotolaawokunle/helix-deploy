<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function execute(User $user, PersonalAccessToken $token): void
    {
        AuditLog::record(
            operation: 'api_token.revoked',
            resource: $user,
            beforeState: [
                'tokenId' => (string) $token->getKey(),
                'name' => $token->name,
            ],
        );

        $token->delete();
    }
}
