<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum CloudProvider: string
{
    case HETZNER = 'hetzner';
    case DIGITALOCEAN = 'digitalocean';
    case AWS = 'aws';

    public function label(): string
    {
        return match ($this) {
            self::HETZNER => 'Hetzner',
            self::DIGITALOCEAN => 'DigitalOcean',
            self::AWS => 'AWS',
        };
    }

    public function credentialName(): string
    {
        return 'cloud_provider:'.$this->value;
    }

    public static function fromServerProvider(ServerProvider $provider): ?self
    {
        return match ($provider) {
            ServerProvider::HETZNER => self::HETZNER,
            ServerProvider::DIGITALOCEAN => self::DIGITALOCEAN,
            ServerProvider::AWS => self::AWS,
            default => null,
        };
    }

    public function toServerProvider(): ServerProvider
    {
        return match ($this) {
            self::HETZNER => ServerProvider::HETZNER,
            self::DIGITALOCEAN => ServerProvider::DIGITALOCEAN,
            self::AWS => ServerProvider::AWS,
        };
    }
}
