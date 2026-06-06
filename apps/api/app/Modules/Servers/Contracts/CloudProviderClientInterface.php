<?php

declare(strict_types=1);

namespace App\Modules\Servers\Contracts;

use App\Modules\Servers\DTOs\CloudInstanceDTO;

interface CloudProviderClientInterface
{
    /**
     * @return list<CloudInstanceDTO>
     */
    public function listInstances(string $credentialPayload): array;
}
