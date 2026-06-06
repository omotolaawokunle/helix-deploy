<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\Enums\ApiTokenAbility;
use Laravel\Sanctum\NewAccessToken;

class CreateApiTokenAction
{
    public function execute(User $user, string $name, ApiTokenAbility $ability): NewAccessToken
    {
        $token = $user->createToken($name, $ability->toSanctumAbilities());

        AuditLog::record(
            operation: 'api_token.created',
            resource: $user,
            afterState: [
                'tokenId' => (string) $token->accessToken->getKey(),
                'name' => $name,
                'ability' => $ability->value,
            ],
        );

        return $token;
    }
}
