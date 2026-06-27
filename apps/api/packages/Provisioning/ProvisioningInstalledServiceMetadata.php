<?php

declare(strict_types=1);

namespace App\Packages\Provisioning;

use App\Packages\Provisioning\DTOs\ServiceVersionDefinition;

class ProvisioningInstalledServiceMetadata
{
    /**
     * @param array<string, mixed> $provisioningOptions
     * @return array<string, mixed>
     */
    public function forScript(string $scriptName, array $provisioningOptions): array
    {
        $metadata = [
            'installed' => true,
            'installed_at' => now()->toIso8601String(),
            'idempotent' => true,
        ];

        $version = $this->resolveVersion($scriptName, $provisioningOptions);

        if ($version !== null) {
            $metadata['version'] = $version;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $provisioningOptions
     */
    private function resolveVersion(string $scriptName, array $provisioningOptions): ?string
    {
        $catalog = new ProvisioningVersionCatalog();
        $definition = $catalog->forService($scriptName);

        if ($definition === null) {
            return null;
        }

        $value = $provisioningOptions[$definition->optionKey] ?? null;

        if ($value === null || $value === '') {
            return (string) $definition->default;
        }

        return (string) $value;
    }
}
