<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Contracts\GitProviderClientInterface;
use App\Modules\Sites\Enums\GitProvider;
use InvalidArgumentException;

class GitProviderClientFactory
{
    public function __construct(
        private readonly GitHubGitProviderClient $github,
        private readonly GitLabGitProviderClient $gitlab,
        private readonly BitbucketGitProviderClient $bitbucket,
    ) {
    }

    public function for(GitProvider $provider): GitProviderClientInterface
    {
        return match ($provider) {
            GitProvider::GITHUB => $this->github,
            GitProvider::GITLAB => $this->gitlab,
            GitProvider::BITBUCKET => $this->bitbucket,
        };
    }

    public function forValue(string $provider): GitProviderClientInterface
    {
        $resolved = GitProvider::tryFrom($provider);

        if ($resolved === null) {
            throw new InvalidArgumentException('Unsupported git provider.');
        }

        return $this->for($resolved);
    }
}
