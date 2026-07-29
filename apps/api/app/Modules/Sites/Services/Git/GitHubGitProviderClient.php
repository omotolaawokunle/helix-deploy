<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Contracts\GitProviderClientInterface;
use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;
use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubGitProviderClient implements GitProviderClientInterface
{
    private const API_BASE = 'https://api.github.com';

    private const MAX_PAGES = 10;

    public function __construct(
        private readonly GitCloneUrlBuilder $cloneUrlBuilder,
    ) {
    }

    /**
     * @return list<GitRepositoryDTO>
     */
    public function listRepositories(string $token): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->get(self::API_BASE.'/user/repos', [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'visibility' => 'all',
                    'affiliation' => 'owner,collaborator,organization_member',
                ])
                ->throw();

            /** @var list<array<string, mixed>> $pageItems */
            $pageItems = $response->json() ?? [];
            $items = array_merge($items, $pageItems);

            if (! $this->hasNextPage($response) || $pageItems === []) {
                break;
            }
        }

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
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
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

    private function hasNextPage(Response $response): bool
    {
        $link = $response->header('Link');

        if (! is_string($link) || $link === '') {
            return false;
        }

        return str_contains($link, 'rel="next"');
    }
}
