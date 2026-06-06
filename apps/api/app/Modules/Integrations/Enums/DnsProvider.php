<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

enum DnsProvider: string
{
    case CLOUDFLARE = 'cloudflare';
    case DIGITALOCEAN = 'digitalocean';

    public function label(): string
    {
        return match ($this) {
            self::CLOUDFLARE => 'Cloudflare',
            self::DIGITALOCEAN => 'DigitalOcean',
        };
    }

    public function credentialName(): string
    {
        return 'dns_provider:'.$this->value;
    }
}
