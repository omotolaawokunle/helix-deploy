<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

readonly class SiteDnsConfigurationDTO
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public bool $autoCreateDns,
        public bool $includeWwwAlias,
        public ?string $projectDnsZoneId,
        public string $domain,
        public array $aliases,
        public ?string $projectId,
    ) {
    }
}
