<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Services\Git\GitCloneUrlBuilder;

it('embeds provider specific credentials into clone urls', function (): void {
    $builder = new GitCloneUrlBuilder();

    $githubUrl = $builder->build(
        GitProvider::GITHUB,
        'token-value',
        'https://github.com/acme/app.git',
    );

    expect($githubUrl)->toBe('https://x-access-token:token-value@github.com/acme/app.git');
});
