<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services\Git;

use App\Modules\Sites\Contracts\GitProviderClientInterface;
use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;
use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Support\Facades\Http;

class GitLabGitProviderClient implements GitProviderClientInterface
{
    private const API_BASE = 'https://gitlab.com/api/v4';

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
            ->get(self::API_BASE.'/projects', [
                'membership' => true,
                'per_page' => 100,
                'order_by' => 'updated_at',
            ])
            ->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json();

        return array_map(function (array $item): GitRepositoryDTO {
            $fullName = (string) ($item['path_with_namespace'] ?? '');

            return new GitRepositoryDTO(
                id: (string) ($item['id'] ?? $fullName),
                name: (string) ($item['name'] ?? ''),
                fullName: $fullName,
                cloneUrl: (string) ($item['http_url_to_repo'] ?? ''),
                defaultBranch: (string) ($item['default_branch'] ?? 'main'),
                isPrivate: (bool) ($item['visibility'] ?? 'private') !== 'public',
            );
        }, $items);
    }

    /**
     * @return list<GitBranchDTO>
     */
    public function listBranches(string $token, string $owner, string $repo): array
    {
        $projectPath = rawurlencode($owner.'/'.$repo);

        $response = Http::withToken($token)
            ->get(self::API_BASE.'/projects/'.$projectPath.'/repository/branches', [
                'per_page' => 100,
            ])
            ->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json();

        return array_map(
            static fn (array $item): GitBranchDTO => new GitBranchDTO(
                name: (string) ($item['name'] ?? ''),
                isDefault: (bool) ($item['default'] ?? false),
            ),
            $items,
        );
    }

    public function buildAuthenticatedCloneUrl(string $token, string $repositoryUrl): string
    {
        return $this->cloneUrlBuilder->build(GitProvider::GITLAB, $token, $repositoryUrl);
    }
}
