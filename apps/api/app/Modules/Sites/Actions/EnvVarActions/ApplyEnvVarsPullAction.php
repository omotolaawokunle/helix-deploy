<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\EnvVarPullStrategy;
use App\Modules\Sites\Events\EnvVarsPulled;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\EnvFileManager;
use App\Modules\Sites\Services\EnvFileParser;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;

final class ApplyEnvVarsPullAction
{
    public function __construct(
        private readonly EnvFileManager $envFileManager,
        private readonly EnvFileParser $envFileParser,
        private readonly CredentialVault $credentialVault,
        private readonly SSHManager $sshManager,
    ) {
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    public function execute(Site $site, Organization $org, EnvVarPullStrategy $strategy): array
    {
        $server = $site->server;
        abort_if($server === null, 404);

        return $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site, $org, $strategy, $server): array {
            $content = $this->envFileManager->read($site, $connection);

            if ($content === '') {
                return ['created' => 0, 'updated' => 0, 'deleted' => 0];
            }

            $parsed = $this->envFileParser->parse($content);
            $serverEntries = $parsed['entries'];

            $helixCredentials = Credential::query()
                ->forOrganization($org)
                ->where('credentialable_type', $site->getMorphClass())
                ->where('credentialable_id', (string) $site->getKey())
                ->ofType(CredentialType::ENV_VAR)
                ->get()
                ->keyBy('name');

            $created = 0;
            $updated = 0;
            $deleted = 0;

            foreach ($serverEntries as $key => $value) {
                $credential = $helixCredentials->get($key);

                if ($credential === null) {
                    $this->credentialVault->storeSecret($org, $site, $key, $value);
                    $created++;
                    sodium_memzero($value);

                    continue;
                }

                if ($strategy === EnvVarPullStrategy::ADD_NEW) {
                    sodium_memzero($value);

                    continue;
                }

                $existingValue = $this->credentialVault->getSecret((string) $credential->getKey(), $org);

                if ($existingValue !== $value) {
                    $this->credentialVault->updateSecret((string) $credential->getKey(), $org, $value);
                    $updated++;
                }

                sodium_memzero($existingValue);
                sodium_memzero($value);
            }

            if ($strategy === EnvVarPullStrategy::MIRROR_SERVER) {
                foreach ($helixCredentials as $key => $credential) {
                    if (! array_key_exists($key, $serverEntries)) {
                        $this->credentialVault->delete((string) $credential->getKey(), $org);
                        $deleted++;
                    }
                }
            }

            if ($created > 0 || $updated > 0 || $deleted > 0) {
                AuditLog::record(
                    operation: 'env_vars.pulled',
                    resource: $site,
                    afterState: [
                        'site_id' => (string) $site->getKey(),
                        'strategy' => $strategy->value,
                        'created' => $created,
                        'updated' => $updated,
                        'deleted' => $deleted,
                    ],
                );
            }

            event(new EnvVarsPulled(
                serverId: (string) $server->getKey(),
                organizationId: (string) $site->organization_id,
                siteId: (string) $site->getKey(),
                strategy: $strategy->value,
                created: $created,
                updated: $updated,
                deleted: $deleted,
            ));

            return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
        });
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    private function withConnection(Server $server, callable $callback): array
    {
        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            return $callback($connection);
        } finally {
            $connection->disconnect();
        }
    }
}
