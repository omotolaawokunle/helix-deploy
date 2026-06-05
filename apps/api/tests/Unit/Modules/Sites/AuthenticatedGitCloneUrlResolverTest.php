<?php

declare(strict_types=1);

use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\Git\AuthenticatedGitCloneUrlResolver;
use App\Modules\Sites\Services\Git\GitCloneUrlBuilder;
use App\Modules\Sites\Services\Git\GitHubGitProviderClient;
use App\Modules\Sites\Services\Git\GitProviderClientFactory;

it('builds authenticated clone urls for private repositories', function (): void {
    $organization = Organization::query()->make(['id' => (string) \Illuminate\Support\Str::uuid()]);
    $site = Site::query()->make([
        'repository_url' => 'https://github.com/acme/private-app.git',
        'repository_provider' => 'github',
    ]);

    $vault = \Mockery::mock(CredentialVault::class);
    $credential = new \App\Modules\Credentials\Models\Credential([
        'name' => 'git_provider:github',
    ]);

    $vault->shouldReceive('findGitProviderCredential')->once()->andReturn($credential);
    $vault->shouldReceive('getGitProviderToken')->once()->andReturn('secret-token');

    $factory = new GitProviderClientFactory(
        new GitHubGitProviderClient(new GitCloneUrlBuilder()),
        new \App\Modules\Sites\Services\Git\GitLabGitProviderClient(new GitCloneUrlBuilder()),
        new \App\Modules\Sites\Services\Git\BitbucketGitProviderClient(new GitCloneUrlBuilder()),
    );

    $resolver = new AuthenticatedGitCloneUrlResolver($vault, $factory);
    $url = $resolver->resolve($site, $organization);

    expect($url)->toContain('x-access-token')
        ->and($url)->toContain('github.com/acme/private-app.git');
});
