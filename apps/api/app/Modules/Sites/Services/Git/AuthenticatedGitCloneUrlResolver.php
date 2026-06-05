<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Models\Site;

class AuthenticatedGitCloneUrlResolver
{
    public function __construct(
        private readonly CredentialVault $credentialVault,
        private readonly GitProviderClientFactory $clientFactory,
    ) {
    }

    public function resolve(Site $site, Organization $organization): ?string
    {
        $repositoryUrl = $site->repository_url;

        if ($repositoryUrl === null || $repositoryUrl === '') {
            return null;
        }

        $providerValue = $site->repository_provider;

        if ($providerValue === null || $providerValue === '') {
            return $repositoryUrl;
        }

        $provider = GitProvider::tryFrom($providerValue);

        if ($provider === null) {
            return $repositoryUrl;
        }

        $credential = $this->credentialVault->findGitProviderCredential(
            $organization,
            $provider->credentialName(),
        );

        if ($credential === null) {
            return $repositoryUrl;
        }

        $token = $this->credentialVault->getGitProviderToken($organization, $provider->credentialName());

        try {
            return $this->clientFactory->for($provider)->buildAuthenticatedCloneUrl($token, $repositoryUrl);
        } finally {
            sodium_memzero($token);
        }
    }
}
