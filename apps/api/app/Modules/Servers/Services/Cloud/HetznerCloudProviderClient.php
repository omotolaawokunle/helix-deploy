<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services\Cloud;

use App\Modules\Servers\Contracts\CloudProviderClientInterface;
use App\Modules\Servers\DTOs\CloudInstanceDTO;
use Illuminate\Support\Facades\Http;

final class HetznerCloudProviderClient implements CloudProviderClientInterface
{
    private const API_BASE = 'https://api.hetzner.cloud/v1';

    /**
     * @return list<CloudInstanceDTO>
     */
    public function listInstances(string $credentialPayload): array
    {
        $response = Http::withToken($credentialPayload)
            ->acceptJson()
            ->get(self::API_BASE.'/servers', ['per_page' => 50])
            ->throw();

        /** @var array{servers?: list<array<string, mixed>>} $payload */
        $payload = $response->json();
        $servers = $payload['servers'] ?? [];

        return array_map(static function (array $server): CloudInstanceDTO {
            /** @var array<string, mixed> $publicNet */
            $publicNet = is_array($server['public_net'] ?? null) ? $server['public_net'] : [];
            /** @var array<string, mixed> $ipv4 */
            $ipv4 = is_array($publicNet['ipv4'] ?? null) ? $publicNet['ipv4'] : [];
            /** @var array<string, mixed> $serverType */
            $serverType = is_array($server['server_type'] ?? null) ? $server['server_type'] : [];
            /** @var array<string, mixed> $datacenter */
            $datacenter = is_array($server['datacenter'] ?? null) ? $server['datacenter'] : [];
            /** @var array<string, mixed> $location */
            $location = is_array($datacenter['location'] ?? null) ? $datacenter['location'] : [];
            /** @var array<string, mixed> $image */
            $image = is_array($server['image'] ?? null) ? $server['image'] : [];

            $name = (string) ($server['name'] ?? 'server');
            $os = trim(sprintf(
                '%s %s',
                (string) ($image['os_flavor'] ?? $image['name'] ?? ''),
                (string) ($image['os_version'] ?? ''),
            ));

            return new CloudInstanceDTO(
                id: (string) ($server['id'] ?? ''),
                name: $name,
                ipAddress: isset($ipv4['ip']) ? (string) $ipv4['ip'] : null,
                region: isset($location['name']) ? (string) $location['name'] : null,
                serverType: isset($serverType['name']) ? (string) $serverType['name'] : null,
                status: (string) ($server['status'] ?? 'unknown'),
                os: $os !== '' ? $os : null,
            );
        }, $servers);
    }
}
