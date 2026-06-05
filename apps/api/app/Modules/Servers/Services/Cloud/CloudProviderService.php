<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services\Cloud;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\DTOs\AwsCloudCredentialDTO;
use App\Modules\Servers\DTOs\CloudInstanceDTO;
use App\Modules\Servers\Enums\CloudProvider;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

final class CloudProviderService
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly CloudProviderClientFactory $clientFactory,
    ) {}

    /**
     * @return list<array{provider: string, label: string, configured: bool}>
     */
    public function listConfiguredProviders(Organization $organization): array
    {
        $configuredNames = Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::CLOUD_PROVIDER_CREDENTIAL)
            ->pluck('name')
            ->all();

        $configuredLookup = array_fill_keys($configuredNames, true);
        $providers = [];

        foreach (CloudProvider::cases() as $provider) {
            $providers[] = [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'configured' => isset($configuredLookup[$provider->credentialName()]),
            ];
        }

        return $providers;
    }

    public function storeTokenCredential(Organization $organization, CloudProvider $provider, string $token): void
    {
        $this->credentialVault->storeCloudProviderCredential(
            $organization,
            $provider->credentialName(),
            $token,
        );
    }

    public function storeAwsCredential(
        Organization $organization,
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
    ): void {
        $payload = (new AwsCloudCredentialDTO($accessKeyId, $secretAccessKey, $region))->toJson();

        try {
            $this->credentialVault->storeCloudProviderCredential(
                $organization,
                CloudProvider::AWS->credentialName(),
                $payload,
            );
        } finally {
            sodium_memzero($secretAccessKey);
        }
    }

    public function revokeCredential(Organization $organization, CloudProvider $provider): void
    {
        $this->credentialVault->deleteCloudProviderCredential($organization, $provider->credentialName());
    }

    /**
     * @return list<CloudInstanceDTO>
     */
    public function listInstances(Organization $organization, CloudProvider $provider): array
    {
        $credentialPayload = $this->credentialVault->getCloudProviderCredential(
            $organization,
            $provider->credentialName(),
        );

        try {
            return $this->clientFactory->for($provider)->listInstances($credentialPayload);
        } catch (RequestException $exception) {
            throw new RuntimeException('Unable to fetch instances from cloud provider.', previous: $exception);
        } finally {
            sodium_memzero($credentialPayload);
        }
    }

    public function findInstance(
        Organization $organization,
        CloudProvider $provider,
        string $instanceId,
    ): ?CloudInstanceDTO {
        foreach ($this->listInstances($organization, $provider) as $instance) {
            if ($instance->id === $instanceId) {
                return $instance;
            }
        }

        return null;
    }

    public function hasCredential(Organization $organization, CloudProvider $provider): bool
    {
        return $this->credentialVault->findCloudProviderCredential($organization, $provider->credentialName()) !== null;
    }
}
