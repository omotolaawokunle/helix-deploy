<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class SiteDatabaseConnectionResolver
{
    /**
     * @var list<string>
     */
    private const REQUIRED_KEYS = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    public function __construct(
        private readonly EnvVarValueResolver $envVarValueResolver,
    ) {
    }

    public function resolve(Site $site, Organization $org): DatabaseConnectionConfig
    {
        $values = $this->resolvedEnvValues($site, $org);

        foreach (self::REQUIRED_KEYS as $key) {
            if (! isset($values[$key]) || $values[$key] === '') {
                throw ValidationException::withMessages([
                    'database' => [sprintf('Missing required environment variable: %s.', $key)],
                ]);
            }
        }

        $engine = $this->resolveEngine($values['DB_CONNECTION'] ?? null);

        return new DatabaseConnectionConfig(
            engine: $engine,
            host: $values['DB_HOST'],
            port: (int) ($values['DB_PORT'] ?? ($engine === DatabaseEngine::POSTGRESQL ? '5432' : '3306')),
            username: $values['DB_USERNAME'],
            password: $values['DB_PASSWORD'],
            database: $values['DB_DATABASE'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function resolvedEnvValues(Site $site, Organization $org): array
    {
        $credentials = Credential::query()
            ->forOrganization($org)
            ->where('credentialable_type', $site->getMorphClass())
            ->where('credentialable_id', (string) $site->getKey())
            ->ofType(CredentialType::ENV_VAR)
            ->whereIn('name', [
                'DB_CONNECTION',
                'DB_HOST',
                'DB_PORT',
                'DB_DATABASE',
                'DB_USERNAME',
                'DB_PASSWORD',
            ])
            ->get();

        $values = [];

        foreach ($credentials as $credential) {
            $value = $this->envVarValueResolver->resolve($credential, $org);
            $values[(string) $credential->name] = $value;
            sodium_memzero($value);
        }

        return $values;
    }

    private function resolveEngine(?string $connection): DatabaseEngine
    {
        $normalized = strtolower(trim((string) $connection));

        if (in_array($normalized, ['mysql', 'mariadb'], true)) {
            return DatabaseEngine::MYSQL;
        }

        if (in_array($normalized, ['pgsql', 'postgres', 'postgresql'], true)) {
            return DatabaseEngine::POSTGRESQL;
        }

        if ($normalized === '') {
            return DatabaseEngine::POSTGRESQL;
        }

        throw new AuthorizationException('Unsupported database connection type for browsing.');
    }
}
