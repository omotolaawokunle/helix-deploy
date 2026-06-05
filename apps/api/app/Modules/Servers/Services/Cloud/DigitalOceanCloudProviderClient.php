<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services\Cloud;

use App\Modules\Servers\Contracts\CloudProviderClientInterface;
use App\Modules\Servers\DTOs\CloudInstanceDTO;
use Illuminate\Support\Facades\Http;

final class DigitalOceanCloudProviderClient implements CloudProviderClientInterface
{
    private const API_BASE = 'https://api.digitalocean.com/v2';

    /**
     * @return list<CloudInstanceDTO>
     */
    public function listInstances(string $credentialPayload): array
    {
        $response = Http::withToken($credentialPayload)
            ->acceptJson()
            ->get(self::API_BASE.'/droplets', ['per_page' => 50])
            ->throw();

        /** @var array{droplets?: list<array<string, mixed>>} $payload */
        $payload = $response->json();
        $droplets = $payload['droplets'] ?? [];

        return array_map(static function (array $droplet): CloudInstanceDTO {
            /** @var array<string, mixed> $networks */
            $networks = is_array($droplet['networks'] ?? null) ? $droplet['networks'] : [];
            /** @var list<array<string, mixed>> $v4 */
            $v4 = is_array($networks['v4'] ?? null) ? $networks['v4'] : [];
            /** @var array<string, mixed> $region */
            $region = is_array($droplet['region'] ?? null) ? $droplet['region'] : [];
            /** @var array<string, mixed> $image */
            $image = is_array($droplet['image'] ?? null) ? $droplet['image'] : [];

            $publicIp = null;
            foreach ($v4 as $network) {
                if (($network['type'] ?? null) === 'public' && isset($network['ip_address'])) {
                    $publicIp = (string) $network['ip_address'];
                    break;
                }
            }

            $os = trim(sprintf(
                '%s %s',
                (string) ($image['distribution'] ?? ''),
                (string) ($image['name'] ?? ''),
            ));

            return new CloudInstanceDTO(
                id: (string) ($droplet['id'] ?? ''),
                name: (string) ($droplet['name'] ?? 'droplet'),
                ipAddress: $publicIp,
                region: isset($region['slug']) ? (string) $region['slug'] : null,
                serverType: isset($droplet['size_slug']) ? (string) $droplet['size_slug'] : null,
                status: (string) ($droplet['status'] ?? 'unknown'),
                os: $os !== '' ? $os : null,
            );
        }, $droplets);
    }
}
