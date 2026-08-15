<?php

declare(strict_types=1);

use App\Modules\Sites\Services\Git\BitbucketGitProviderClient;
use App\Modules\Sites\Services\Git\GitCloneUrlBuilder;
use Illuminate\Support\Facades\Http;

it('lists repositories via workspace-scoped endpoints instead of the removed cross-workspace list', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.bitbucket.org/2.0/user/workspaces*' => Http::response([
            'values' => [
                [
                    'type' => 'workspace_access',
                    'workspace' => [
                        'slug' => 'acme',
                        'type' => 'workspace_base',
                    ],
                ],
            ],
        ]),
        'api.bitbucket.org/2.0/repositories/acme*' => Http::response([
            'values' => [
                [
                    'name' => 'private-app',
                    'full_name' => 'acme/private-app',
                    'is_private' => true,
                    'mainbranch' => ['name' => 'main'],
                    'links' => [
                        'clone' => [
                            ['name' => 'https', 'href' => 'https://bitbucket.org/acme/private-app.git'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $stored = json_encode(['email' => 'dev@example.com', 'token' => 'ATATT_test_token'], JSON_THROW_ON_ERROR);
    $client = new BitbucketGitProviderClient(new GitCloneUrlBuilder);
    $repositories = $client->listRepositories($stored);

    expect($repositories)->toHaveCount(1)
        ->and($repositories[0]->fullName)->toBe('acme/private-app')
        ->and($repositories[0]->isPrivate)->toBeTrue();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'api.bitbucket.org/2.0/user/workspaces')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('dev@example.com:ATATT_test_token'));
    });

    Http::assertSent(function ($request): bool {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return $path === '/2.0/repositories/acme'
            && str_contains($request->url(), 'role=member');
    });

    Http::assertNotSent(function ($request): bool {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return $path === '/2.0/repositories';
    });
});

it('uses bearer auth for bitbucket access tokens without an email', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.bitbucket.org/2.0/user/workspaces*' => Http::response([
            'values' => [
                [
                    'workspace' => ['slug' => 'acme'],
                ],
            ],
        ]),
        'api.bitbucket.org/2.0/repositories/acme/app/refs/branches*' => Http::response([
            'values' => [['name' => 'main']],
        ]),
        'api.bitbucket.org/2.0/repositories/acme*' => Http::response(['values' => []]),
    ]);

    $client = new BitbucketGitProviderClient(new GitCloneUrlBuilder);
    $client->listRepositories('workspace-access-token');
    $branches = $client->listBranches('workspace-access-token', 'acme', 'app');

    expect($branches)->toHaveCount(1)
        ->and($branches[0]->name)->toBe('main');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer workspace-access-token');
    });
});

it('embeds the api token git username into bitbucket clone urls', function (): void {
    $client = new BitbucketGitProviderClient(new GitCloneUrlBuilder);
    $stored = json_encode(['email' => 'dev@example.com', 'token' => 'ATATT_clone_token'], JSON_THROW_ON_ERROR);

    $url = $client->buildAuthenticatedCloneUrl($stored, 'https://bitbucket.org/acme/private-app.git');

    expect($url)->toBe('https://x-bitbucket-api-token-auth:ATATT_clone_token@bitbucket.org/acme/private-app.git');
});
