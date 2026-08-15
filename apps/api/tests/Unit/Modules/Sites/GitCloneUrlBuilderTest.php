<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Services\Git\GitCloneUrlBuilder;

it('embeds provider specific credentials into clone urls', function (): void {
    $builder = new GitCloneUrlBuilder;

    $githubUrl = $builder->build(
        GitProvider::GITHUB,
        'token-value',
        'https://github.com/acme/app.git',
    );

    $bitbucketUrl = $builder->build(
        GitProvider::BITBUCKET,
        'token-value',
        'https://bitbucket.org/acme/app.git',
        'x-bitbucket-api-token-auth',
    );

    expect($githubUrl)->toBe('https://x-access-token:token-value@github.com/acme/app.git')
        ->and($bitbucketUrl)->toBe('https://x-bitbucket-api-token-auth:token-value@bitbucket.org/acme/app.git');
});
