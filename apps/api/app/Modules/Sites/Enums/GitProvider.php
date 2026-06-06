<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum GitProvider: string
{
    case GITHUB = 'github';
    case GITLAB = 'gitlab';
    case BITBUCKET = 'bitbucket';

    public function label(): string
    {
        return match ($this) {
            self::GITHUB => 'GitHub',
            self::GITLAB => 'GitLab',
            self::BITBUCKET => 'Bitbucket',
        };
    }

    public function credentialName(): string
    {
        return 'git_provider:'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $provider): string => $provider->value, self::cases());
    }
}
