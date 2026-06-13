<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Servers\Exceptions\UncontrollableServiceException;
use App\Modules\Servers\Models\Server;

class InstalledServiceRegistry
{
    /**
     * @var array<string, string>
     */
    private const array STATIC_UNITS = [
        'nginx' => 'nginx',
        'mysql' => 'mysql',
        'postgresql' => 'postgresql',
        'redis' => 'redis-server',
        'supervisor' => 'supervisor',
        'docker' => 'docker',
    ];

    /**
     * @var array<string, string>
     */
    private const array LABELS = [
        'nginx' => 'Nginx',
        'php' => 'PHP-FPM',
        'mysql' => 'MySQL',
        'postgresql' => 'PostgreSQL',
        'redis' => 'Redis',
        'supervisor' => 'Supervisor',
        'docker' => 'Docker',
        'nodejs' => 'Node.js',
    ];

    /**
     * @return list<string>
     */
    public function controllableKeys(): array
    {
        return array_keys(self::STATIC_UNITS);
    }

    public function isControllable(string $key): bool
    {
        if ($key === 'php') {
            return true;
        }

        return array_key_exists($key, self::STATIC_UNITS);
    }

    public function labelFor(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst($key);
    }

    public function unitFor(Server $server, string $key): string
    {
        if ($key === 'php') {
            $version = $server->php_version ?? '8.3';

            return 'php'.$version.'-fpm';
        }

        if (! array_key_exists($key, self::STATIC_UNITS)) {
            throw new UncontrollableServiceException("Service [{$key}] is not systemd-managed.");
        }

        return self::STATIC_UNITS[$key];
    }

    /**
     * @return list<string>
     */
    public function installedControllableKeys(Server $server): array
    {
        $installed = (array) $server->installed_services;
        $keys = [];

        foreach ($installed as $key => $metadata) {
            if (! is_array($metadata) || ($metadata['installed'] ?? false) !== true) {
                continue;
            }

            if (! $this->isControllable((string) $key)) {
                continue;
            }

            $keys[] = (string) $key;
        }

        sort($keys);

        return $keys;
    }
}
