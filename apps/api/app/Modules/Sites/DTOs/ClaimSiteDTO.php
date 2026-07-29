<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

final readonly class ClaimSiteDTO
{
    public function __construct(
        public string $repositoryUrl,
        public string $repositoryProvider,
        public string $deployBranch,
        public bool $autoDeployEnabled,
    ) {
    }
}
