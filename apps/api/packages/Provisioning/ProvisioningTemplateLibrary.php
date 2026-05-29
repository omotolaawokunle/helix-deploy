<?php

declare(strict_types=1);

namespace App\Packages\Provisioning;

use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;

class ProvisioningTemplateLibrary
{
    /**
     * @return array<string, array{scripts: list<string>, defaults: array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            'laravel-stack' => [
                'scripts' => ['create-deploy-user', 'nginx', 'php', 'mysql', 'redis', 'supervisor'],
                'defaults' => ['phpVersion' => PhpVersion::V8_3->value],
            ],
            'node-api-stack' => [
                'scripts' => ['create-deploy-user', 'nginx', 'nodejs', 'supervisor'],
                'defaults' => ['nodeVersion' => NodejsVersion::V20->value],
            ],
            'static-frontend-stack' => [
                'scripts' => ['create-deploy-user', 'nginx'],
                'defaults' => [],
            ],
            'worker-stack' => [
                'scripts' => ['create-deploy-user', 'php', 'redis', 'supervisor'],
                'defaults' => ['phpVersion' => PhpVersion::V8_3->value],
            ],
        ];
    }

    /**
     * @return array{scripts: list<string>, defaults: array<string, mixed>}|null
     */
    public function find(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }
}
