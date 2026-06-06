<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

use App\Modules\Integrations\DTOs\CloudflareDnsRecordDTO;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;

interface CloudflareClientInterface extends DnsZoneClientInterface
{
}
