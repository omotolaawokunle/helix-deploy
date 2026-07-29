<?php

declare(strict_types=1);

use App\Modules\Sites\Services\Git\GitCloneUrlBuilder;
use App\Modules\Sites\Services\Git\GitHubGitProviderClient;
use Illuminate\Support\Facades\Http;

it('lists private and public github repositories with visibility=all', function (): void {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'name' => 'private-app',
                'full_name' => 'acme/private-app',
                'clone_url' => 'https://github.com/acme/private-app.git',
                'default_branch' => 'main',
                'private' => true,
            ],
            [
                'name' => 'public-app',
                'full_name' => 'acme/public-app',
                'clone_url' => 'https://github.com/acme/public-app.git',
                'default_branch' => 'main',
                'private' => false,
            ],
        ], 200, [
            'Link' => '',
        ]),
    ]);

    $client = new GitHubGitProviderClient(new GitCloneUrlBuilder());
    $repositories = $client->listRepositories('ghp_test_token');

    expect($repositories)->toHaveCount(2)
        ->and($repositories[0]->isPrivate)->toBeTrue()
        ->and($repositories[1]->isPrivate)->toBeFalse();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.github.com/user/repos?per_page=100&page=1&sort=updated&visibility=all&affiliation=owner%2Ccollaborator%2Corganization_member'
            || (
                str_contains($request->url(), 'api.github.com/user/repos')
                && str_contains($request->url(), 'visibility=all')
                && str_contains($request->url(), 'affiliation=owner')
            );
    });
});

it('paginates github repository listings', function (): void {
    Http::fake(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = (int) ($query['page'] ?? 1);

        if ($page === 1) {
            return Http::response(
                array_map(
                    static fn (int $i): array => [
                        'name' => "repo-{$i}",
                        'full_name' => "acme/repo-{$i}",
                        'clone_url' => "https://github.com/acme/repo-{$i}.git",
                        'default_branch' => 'main',
                        'private' => $i % 2 === 0,
                    ],
                    range(1, 100),
                ),
                200,
                ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"'],
            );
        }

        return Http::response([
            [
                'name' => 'private-last',
                'full_name' => 'acme/private-last',
                'clone_url' => 'https://github.com/acme/private-last.git',
                'default_branch' => 'main',
                'private' => true,
            ],
        ]);
    });

    $client = new GitHubGitProviderClient(new GitCloneUrlBuilder());
    $repositories = $client->listRepositories('ghp_test_token');

    expect($repositories)->toHaveCount(101)
        ->and($repositories[100]->fullName)->toBe('acme/private-last')
        ->and($repositories[100]->isPrivate)->toBeTrue();
});
