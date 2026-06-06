<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Cloudflare;

use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\DTOs\CloudflareDnsRecordDTO;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;

final class FakeCloudflareClient implements CloudflareClientInterface
{
    public bool $verifyTokenResult = true;

    /** @var list<CloudflareZoneDTO> */
    public array $zones = [];

    /** @var array<string, CloudflareDnsRecordDTO> */
    public array $existingRecords = [];

    /** @var array<string, string> */
    public array $createdRecords = [];

    /** @var list<string> */
    public array $deletedRecordIds = [];

    public function verifyToken(string $token): bool
    {
        return $this->verifyTokenResult;
    }

    public function listZones(string $token): array
    {
        return $this->zones;
    }

    public function recordExists(string $token, string $zoneId, string $hostname): bool
    {
        return $this->findARecord($token, $zoneId, $hostname) !== null;
    }

    public function findARecord(string $token, string $zoneId, string $hostname): ?CloudflareDnsRecordDTO
    {
        return $this->existingRecords[$zoneId.':'.$hostname] ?? null;
    }

    public function createARecord(
        string $token,
        string $zoneId,
        string $recordName,
        string $ipAddress,
        bool $proxied = false,
    ): string {
        $recordId = 'cf-record-'.count($this->createdRecords) + 1;
        $this->createdRecords[$recordId] = $zoneId.':'.$recordName.':'.$ipAddress;

        return $recordId;
    }

    public function deleteRecord(string $token, string $zoneId, string $recordId): void
    {
        $this->deletedRecordIds[] = $recordId;
        unset($this->createdRecords[$recordId]);
    }

    public function seedExistingRecord(
        string $zoneId,
        string $hostname,
        string $ipAddress,
        string $recordId = 'cf-existing-1',
    ): void {
        $this->existingRecords[$zoneId.':'.$hostname] = new CloudflareDnsRecordDTO(
            id: $recordId,
            name: $hostname,
            type: 'A',
            content: $ipAddress,
            proxied: false,
        );
    }
}
