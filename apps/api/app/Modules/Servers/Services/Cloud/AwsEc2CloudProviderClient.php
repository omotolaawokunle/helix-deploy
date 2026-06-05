<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services\Cloud;

use App\Modules\Servers\Contracts\CloudProviderClientInterface;
use App\Modules\Servers\DTOs\AwsCloudCredentialDTO;
use App\Modules\Servers\DTOs\CloudInstanceDTO;
use Aws\Ec2\Ec2Client;

final class AwsEc2CloudProviderClient implements CloudProviderClientInterface
{
    /**
     * @return list<CloudInstanceDTO>
     */
    public function listInstances(string $credentialPayload): array
    {
        $credential = AwsCloudCredentialDTO::fromJson($credentialPayload);

        $client = new Ec2Client([
            'version' => 'latest',
            'region' => $credential->region,
            'credentials' => [
                'key' => $credential->accessKeyId,
                'secret' => $credential->secretAccessKey,
            ],
        ]);

        $result = $client->describeInstances([
            'Filters' => [
                [
                    'Name' => 'instance-state-name',
                    'Values' => ['running', 'pending'],
                ],
            ],
        ]);
        /** @var list<array<string, mixed>> $reservations */
        $reservations = $result->get('Reservations') ?? [];

        $instances = [];

        foreach ($reservations as $reservation) {
            /** @var list<array<string, mixed>> $reservationInstances */
            $reservationInstances = is_array($reservation['Instances'] ?? null) ? $reservation['Instances'] : [];

            foreach ($reservationInstances as $instance) {
                $instances[] = $this->mapInstance($instance);
            }
        }

        return $instances;
    }

    /**
     * @param array<string, mixed> $instance
     */
    private function mapInstance(array $instance): CloudInstanceDTO
    {
        $name = $this->resolveNameTag($instance);
        $publicIp = isset($instance['PublicIpAddress']) ? (string) $instance['PublicIpAddress'] : null;
        /** @var array<string, mixed>|null $placement */
        $placement = is_array($instance['Placement'] ?? null) ? $instance['Placement'] : null;
        /** @var array<string, mixed>|null $state */
        $state = is_array($instance['State'] ?? null) ? $instance['State'] : null;

        return new CloudInstanceDTO(
            id: (string) ($instance['InstanceId'] ?? ''),
            name: $name,
            ipAddress: $publicIp,
            region: isset($placement['AvailabilityZone']) ? (string) $placement['AvailabilityZone'] : null,
            serverType: isset($instance['InstanceType']) ? (string) $instance['InstanceType'] : null,
            status: (string) ($state['Name'] ?? 'unknown'),
            os: isset($instance['PlatformDetails']) ? (string) $instance['PlatformDetails'] : null,
        );
    }

    /**
     * @param array<string, mixed> $instance
     */
    private function resolveNameTag(array $instance): string
    {
        /** @var list<array<string, mixed>> $tags */
        $tags = is_array($instance['Tags'] ?? null) ? $instance['Tags'] : [];

        foreach ($tags as $tag) {
            if (($tag['Key'] ?? null) === 'Name' && isset($tag['Value']) && (string) $tag['Value'] !== '') {
                return (string) $tag['Value'];
            }
        }

        return (string) ($instance['InstanceId'] ?? 'instance');
    }
}
