<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\DTOs\EnvVarsPullDiff;
use App\Modules\Sites\Models\Site;

final class EnvVarsPullComparer
{
    public function __construct(
        private readonly EnvFileParser $envFileParser,
        private readonly EnvVarValueResolver $envVarValueResolver,
    ) {
    }

    public function compare(Site $site, Organization $org, string $serverContent): EnvVarsPullDiff
    {
        $serverFileExists = $serverContent !== '';
        $parsed = $this->envFileParser->parse($serverContent);
        $serverEntries = $parsed['entries'];
        $skipped = $parsed['skipped'];

        $helixCredentials = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $site->getMorphClass())
            ->where('credentialable_id', (string) $site->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->get()
            ->keyBy('name');

        $new = [];
        $changed = [];
        $unchanged = [];

        foreach ($serverEntries as $key => $serverValue) {
            $credential = $helixCredentials->get($key);

            if ($credential === null) {
                $new[] = $key;
                sodium_memzero($serverValue);

                continue;
            }

            $helixValue = $this->envVarValueResolver->resolve($credential, $org);

            if ($helixValue === $serverValue) {
                $unchanged[] = $key;
            } else {
                $changed[] = $key;
            }

            sodium_memzero($helixValue);
            sodium_memzero($serverValue);
        }

        $helixOnly = $helixCredentials
            ->keys()
            ->diff(array_keys($serverEntries))
            ->sort()
            ->values()
            ->all();

        sort($new);
        sort($changed);
        sort($unchanged);

        return new EnvVarsPullDiff(
            serverFileExists: $serverFileExists,
            new: $new,
            changed: $changed,
            unchanged: $unchanged,
            helixOnly: $helixOnly,
            skipped: $skipped,
        );
    }
}
