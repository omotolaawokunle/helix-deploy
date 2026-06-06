<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Enums\GitProvider;
use InvalidArgumentException;

class GitCloneUrlBuilder
{
    public function build(GitProvider $provider, string $token, string $repositoryUrl): string
    {
        $parts = parse_url($repositoryUrl);

        if ($parts === false || ! isset($parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Invalid repository URL.');
        }

        $scheme = $parts['scheme'] ?? 'https';
        $username = match ($provider) {
            GitProvider::GITHUB => 'x-access-token',
            GitProvider::GITLAB => 'oauth2',
            GitProvider::BITBUCKET => 'x-token-auth',
        };

        return sprintf(
            '%s://%s:%s@%s%s',
            $scheme,
            rawurlencode($username),
            rawurlencode($token),
            $parts['host'],
            $parts['path'],
        );
    }
}
