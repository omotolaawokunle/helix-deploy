<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

use App\Modules\Sites\DTOs\GitBranchDTO;
use App\Modules\Sites\DTOs\GitRepositoryDTO;

interface GitProviderClientInterface
{
    /**
     * @return list<GitRepositoryDTO>
     */
    public function listRepositories(string $token): array;

    /**
     * @return list<GitBranchDTO>
     */
    public function listBranches(string $token, string $owner, string $repo): array;

    public function buildAuthenticatedCloneUrl(string $token, string $repositoryUrl): string;
}
