<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

class ServerServiceCredentialRegistry
{
    /**
     * @return array{serviceKey: string, label: string}|null
     */
    public function metadataForName(string $name): ?array
    {
        if (str_ends_with($name, '-postgresql-deploy-password')) {
            return ['serviceKey' => 'postgresql', 'label' => 'PostgreSQL deploy password'];
        }

        if (str_ends_with($name, '-mysql-deploy-password')) {
            return ['serviceKey' => 'mysql', 'label' => 'MySQL deploy password'];
        }

        if (str_ends_with($name, '-redis-password')) {
            return ['serviceKey' => 'redis', 'label' => 'Redis password'];
        }

        return null;
    }
}
