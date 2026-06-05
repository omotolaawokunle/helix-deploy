<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Contracts\GitProviderClientInterface;
use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;
use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Support\Facades\Http;

class GitHubGitProviderClient implements GitProviderClientInterface
{
    private const API_BASE = 'https://api.github.com';

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
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE.'/user/repos', [
                'per_page' => 100,
                'sort' => 'updated',
            ])
            ->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json();

        return array_map(function (array $item): GitRepositoryDTO {
            $fullName = (string) ($item['full_name'] ?? '');
            $defaultBranch = (string) ($item['default_branch'] ?? 'main');

            return new GitRepositoryDTO(
                id: $fullName,
                name: (string) ($item['name'] ?? ''),
                fullName: $fullName,
                cloneUrl: (string) ($item['clone_url'] ?? ''),
                defaultBranch: $defaultBranch,
                isPrivate: (bool) ($item['private'] ?? false),
            );
        }, $items);
    }

    /**
     * @return list<GitBranchDTO>
     */
    public function listBranches(string $token, string $owner, string $repo): array
    {
        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get(self::API_BASE.'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/branches', [
                'per_page' => 100,
            ])
            ->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json();

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
        return $this->cloneUrlBuilder->build(GitProvider::GITHUB, $token, $repositoryUrl);
    }
}
