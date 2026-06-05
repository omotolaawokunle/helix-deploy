<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Contracts\GitProviderClientInterface;
use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;
use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Support\Facades\Http;

class BitbucketGitProviderClient implements GitProviderClientInterface
{
    private const API_BASE = 'https://api.bitbucket.org/2.0';

    public function __construct(
        private readonly GitCloneUrlBuilder $cloneUrlBuilder,
    ) {
    }

    /**
     * @return list<GitRepositoryDTO>
     */
    public function listRepositories(string $token): array
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE.'/repositories', [
                'role' => 'member',
                'pagelen' => 100,
            ])
            ->throw();

        /** @var array{values?: list<array<string, mixed>>} $payload */
        $payload = $response->json();
        /** @var list<array<string, mixed>> $items */
        $items = $payload['values'] ?? [];

        return array_map(function (array $item): GitRepositoryDTO {
            $fullName = (string) ($item['full_name'] ?? '');
            $links = is_array($item['links'] ?? null) ? $item['links'] : [];
            $cloneLinks = is_array($links['clone'] ?? null) ? $links['clone'] : [];
            $cloneUrl = '';

            foreach ($cloneLinks as $link) {
                if (is_array($link) && ($link['name'] ?? null) === 'https') {
                    $cloneUrl = (string) ($link['href'] ?? '');

                    break;
                }
            }

            $mainBranch = is_array($item['mainbranch'] ?? null) ? $item['mainbranch'] : [];

            return new GitRepositoryDTO(
                id: $fullName,
                name: (string) ($item['name'] ?? ''),
                fullName: $fullName,
                cloneUrl: $cloneUrl,
                defaultBranch: (string) ($mainBranch['name'] ?? 'main'),
                isPrivate: (bool) ($item['is_private'] ?? true),
            );
        }, $items);
    }

    /**
     * @return list<GitBranchDTO>
     */
    public function listBranches(string $token, string $owner, string $repo): array
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE.'/repositories/'.rawurlencode($owner).'/'.rawurlencode($repo).'/refs/branches', [
                'pagelen' => 100,
            ])
            ->throw();

        /** @var array{values?: list<array<string, mixed>>} $payload */
        $payload = $response->json();
        /** @var list<array<string, mixed>> $items */
        $items = $payload['values'] ?? [];

        return array_map(
            static fn (array $item): GitBranchDTO => new GitBranchDTO(
                name: (string) ($item['name'] ?? ''),
                isDefault: false,
            ),
            $items,
        );
    }

    public function buildAuthenticatedCloneUrl(string $token, string $repositoryUrl): string
    {
        return $this->cloneUrlBuilder->build(GitProvider::BITBUCKET, $token, $repositoryUrl);
    }
}
