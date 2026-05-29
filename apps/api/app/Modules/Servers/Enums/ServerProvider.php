<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ServerProvider: string
{
    case HETZNER = 'hetzner';
    case DIGITALOCEAN = 'digitalocean';
    case AWS = 'aws';
    case VULTR = 'vultr';
    case GENERIC = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::HETZNER => 'Hetzner',
            self::DIGITALOCEAN => 'DigitalOcean',
            self::AWS => 'AWS',
            self::VULTR => 'Vultr',
            self::GENERIC => 'Generic',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HETZNER => 'info',
            self::DIGITALOCEAN => 'info',
            self::AWS => 'warning',
            self::VULTR => 'secondary',
            self::GENERIC => 'neutral',
        };
    }
}
