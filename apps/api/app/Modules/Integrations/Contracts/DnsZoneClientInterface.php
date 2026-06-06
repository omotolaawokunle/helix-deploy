<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

use App\Modules\Integrations\DTOs\CloudflareDnsRecordDTO;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;

interface DnsZoneClientInterface
{
    public function verifyToken(string $token): bool;

    /**
     * @return list<CloudflareZoneDTO>
     */
    public function listZones(string $token): array;

    public function recordExists(string $token, string $zoneId, string $hostname): bool;

    public function findARecord(string $token, string $zoneId, string $hostname): ?CloudflareDnsRecordDTO;

    public function createARecord(
        string $token,
        string $zoneId,
        string $recordName,
        string $ipAddress,
        bool $proxied = false,
    ): string;

    public function deleteRecord(string $token, string $zoneId, string $recordId): void;
}
