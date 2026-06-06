<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

use App\Modules\Integrations\DTOs\SiteDnsConfigurationDTO;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;

interface SiteDnsProvisionerInterface
{
    /**
     * @return array<string, mixed> Site attributes to persist during create.
     */
    public function resolveSiteAttributes(Organization $org, SiteDnsConfigurationDTO $configuration): array;

    public function validateForCreate(Organization $org, SiteDnsConfigurationDTO $configuration): void;

    public function provision(Site $site): void;

    public function deleteRecords(Site $site): void;
}
