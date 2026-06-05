<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Services\Git\GitProviderClientFactory;

class GitProviderService
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly GitProviderClientFactory $clientFactory,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConfiguredProviders(Organization $organization): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Credential> $credentials */
        $credentials = Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::GIT_PROVIDER_TOKEN)
            ->orderBy('name')
            ->get();

        return $credentials->map(function (Credential $credential): array {
            $provider = $this->providerFromCredentialName((string) $credential->name);

            return [
                'provider' => $provider?->value,
                'label' => $provider?->label(),
                'configuredAt' => $credential->created_at?->toIso8601String(),
                'lastUsedAt' => $credential->last_used_at?->toIso8601String(),
            ];
        })->filter(static fn (array $item): bool => $item['provider'] !== null)->values()->all();
    }

    public function storeProviderToken(Organization $organization, GitProvider $provider, string $token): void
    {
        $this->credentialVault->storeGitProviderToken(
            $organization,
            $provider->credentialName(),
            $token,
        );
    }

    public function revokeProviderToken(Organization $organization, GitProvider $provider): void
    {
        $credential = $this->credentialVault->findGitProviderCredential(
            $organization,
            $provider->credentialName(),
        );

        if ($credential === null) {
            return;
        }

        $this->credentialVault->delete((string) $credential->getKey(), $organization);
    }

    public function hasProviderToken(Organization $organization, GitProvider $provider): bool
    {
        return $this->credentialVault->findGitProviderCredential(
            $organization,
            $provider->credentialName(),
        ) !== null;
    }

    /**
     * @return list<GitRepositoryDTO>
     */
    public function listRepositories(Organization $organization, GitProvider $provider): array
    {
        $token = $this->credentialVault->getGitProviderToken($organization, $provider->credentialName());

        try {
            return $this->clientFactory->for($provider)->listRepositories($token);
        } finally {
            sodium_memzero($token);
        }
    }

    /**
     * @return list<GitBranchDTO>
     */
    public function listBranches(
        Organization $organization,
        GitProvider $provider,
        string $owner,
        string $repo,
    ): array {
        $token = $this->credentialVault->getGitProviderToken($organization, $provider->credentialName());

        try {
            return $this->clientFactory->for($provider)->listBranches($token, $owner, $repo);
        } finally {
            sodium_memzero($token);
        }
    }

    private function providerFromCredentialName(string $name): ?GitProvider
    {
        foreach (GitProvider::cases() as $provider) {
            if ($provider->credentialName() === $name) {
                return $provider;
            }
        }

        return null;
    }
}
