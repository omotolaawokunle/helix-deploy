<?php

declare(strict_types=1);

namespace App\Packages\Provisioning;

use App\Packages\Provisioning\DTOs\ServiceVersionDefinition;
use App\Packages\Provisioning\Enums\MysqlVersion;
use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;
use App\Packages\Provisioning\Enums\PostgresqlVersion;
use App\Packages\Provisioning\Enums\PythonVersion;

class ProvisioningVersionCatalog
{
    /**
     * @return array<string, ServiceVersionDefinition>
     */
    public function all(): array
    {
        return [
            'php' => new ServiceVersionDefinition(
                serviceKey: 'php',
                optionKey: 'phpVersion',
                label: 'PHP',
                values: PhpVersion::values(),
                default: PhpVersion::V8_3->value,
            ),
            'nodejs' => new ServiceVersionDefinition(
                serviceKey: 'nodejs',
                optionKey: 'nodeVersion',
                label: 'Node.js',
                values: NodejsVersion::values(),
                default: NodejsVersion::V20->value,
            ),
            'postgresql' => new ServiceVersionDefinition(
                serviceKey: 'postgresql',
                optionKey: 'postgresqlVersion',
                label: 'PostgreSQL',
                values: PostgresqlVersion::values(),
                default: PostgresqlVersion::default()->value,
            ),
            'mysql' => new ServiceVersionDefinition(
                serviceKey: 'mysql',
                optionKey: 'mysqlVersion',
                label: 'MySQL',
                values: MysqlVersion::values(),
                default: MysqlVersion::default()->value,
            ),
            'python' => new ServiceVersionDefinition(
                serviceKey: 'python',
                optionKey: 'pythonVersion',
                label: 'Python',
                values: PythonVersion::values(),
                default: PythonVersion::default()->value,
            ),
        ];
    }

    public function forService(string $serviceKey): ?ServiceVersionDefinition
    {
        return $this->all()[$serviceKey] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->all() as $key => $definition) {
            $result[$key] = $definition->toArray();
        }

        return $result;
    }
}
