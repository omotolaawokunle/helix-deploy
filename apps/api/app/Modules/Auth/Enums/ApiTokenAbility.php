<?php

declare(strict_types=1);

namespace App\Modules\Auth\Enums;

enum ApiTokenAbility: string
{
    case Full = 'full';
    case Read = 'read';

    /**
     * @return list<string>
     */
    public function toSanctumAbilities(): array
    {
        return match ($this) {
            self::Full => ['*'],
            self::Read => ['read'],
        };
    }
}
