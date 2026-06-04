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

class EnvFileManager
{
    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
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

        $remotePath = '/var/www/'.$site->domain.'/shared/.env';

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

    private function quoteEnvValue(string $value): string
    {
        $escaped = addcslashes($value, "\\\"\n\r\t\$");

        return '"'.$escaped.'"';
    }
}
