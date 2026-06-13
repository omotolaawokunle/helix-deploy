<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Facades\Cache;

class EnvFileManager
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly SiteDeployPathResolver $deployPathResolver,
    ) {
    }

    public function generate(Site $site, Organization $org): string
    {
        $credentials = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $site->getMorphClass())
            ->where('credentialable_id', (string) $site->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->orderBy('name')
            ->get();

        $lines = [];

        foreach ($credentials as $credential) {
            $value = $this->credentialVault->getSecret((string) $credential->getKey(), $org);
            $lines[] = $credential->name.'='.$this->quoteEnvValue($value);
            sodium_memzero($value);
        }

        if ($lines === []) {
            return '';
        }

        return implode("\n", $lines)."\n";
    }

    public function sync(Site $site, Organization $org, SSHConnectionInterface $ssh): void
    {
        $content = $this->generate($site, $org);
        $count = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $site->getMorphClass())
            ->where('credentialable_id', (string) $site->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->count();

        $remotePath = $this->cachedRemotePath($site) ?? $this->remotePath($site);

        if (! $ssh->upload($content, $remotePath)) {
            throw new \RuntimeException('Failed to upload .env file to server.');
        }

        $ssh->run(sprintf('chmod 640 %s', escapeshellarg($remotePath)))->throw();
        $ssh->run(sprintf('chown deploy:www-data %s', escapeshellarg($remotePath)))->throw();

        AuditLog::record(
            operation: 'env_vars.synced',
            resource: $site,
            afterState: [
                'site_id' => (string) $site->getKey(),
                'count' => $count,
            ],
        );
    }

    public function remotePath(Site $site): string
    {
        return $this->deployPathResolver->defaultEnvPath($site);
    }

    public function read(Site $site, SSHConnectionInterface $ssh): string
    {
        $remotePath = $this->resolveRemotePath($site, $ssh);

        if ($remotePath === null) {
            return '';
        }

        return $ssh->run(sprintf('cat %s', escapeshellarg($remotePath)))->stdout;
    }

    public function resolveRemotePath(Site $site, SSHConnectionInterface $ssh): ?string
    {
        $cached = $this->cachedRemotePath($site);

        if ($cached !== null && $this->remoteFileExists($ssh, $cached)) {
            return $cached;
        }

        foreach ($this->deployPathResolver->envFileCandidates($site) as $candidate) {
            if (! $this->remoteFileExists($ssh, $candidate)) {
                continue;
            }

            Cache::put($this->resolvedPathCacheKey($site), $candidate, now()->addDays(7));

            return $candidate;
        }

        Cache::forget($this->resolvedPathCacheKey($site));

        return null;
    }

    public static function resolvedPathCacheKey(Site $site): string
    {
        return 'env_file_remote_path:'.(string) $site->getKey();
    }

    private function cachedRemotePath(Site $site): ?string
    {
        $cached = Cache::get(self::resolvedPathCacheKey($site));

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    private function remoteFileExists(SSHConnectionInterface $ssh, string $remotePath): bool
    {
        return $ssh->run(sprintf('test -f %s', escapeshellarg($remotePath)))->successful();
    }

    private function quoteEnvValue(string $value): string
    {
        $escaped = addcslashes($value, "\\\"\n\r\t\$");

        return '"'.$escaped.'"';
    }
}
