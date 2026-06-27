<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Events\SiteDatabaseBrowseReady;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteDatabaseConnectionResolver;
use App\Packages\DatabaseBrowser\Contracts\ReadOnlyDatabaseClientInterface;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind;
use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class BrowseSiteDatabaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $siteId,
        public readonly string $organizationId,
        public readonly DatabaseBrowseKind $kind,
        public readonly ?string $table,
        public readonly ?DatabaseRowQuery $rowQuery,
    ) {
        $this->onQueue('commands');
    }

    public function handle(
        SSHManager $sshManager,
        CredentialVault $credentialVault,
        SiteDatabaseConnectionResolver $connectionResolver,
        ReadOnlyDatabaseClientInterface $databaseClient,
        SqlIdentifierValidator $identifierValidator,
    ): void {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null) {
            return;
        }

        $org = $site->organization;

        if ($org === null || (string) $org->getKey() !== $this->organizationId) {
            return;
        }

        $server = $site->server;

        if ($server === null) {
            return;
        }

        $cacheKey = self::cacheKey($this->siteId, $this->kind, $this->table, $this->rowQuery);

        try {
            $config = $connectionResolver->resolve($site, $org);
            $connection = $sshManager->connect($server, $credentialVault)->connect();

            try {
                $database = (string) $config->database;

                $payload = match ($this->kind) {
                    DatabaseBrowseKind::TABLES => [
                        'database' => $database,
                        'engine' => $config->engine->value,
                        'tables' => $databaseClient->listTables($connection, $config, $database),
                    ],
                    DatabaseBrowseKind::ROWS => $this->rowsPayload(
                        $databaseClient,
                        $connection,
                        $config,
                        $database,
                        $identifierValidator,
                    ),
                    DatabaseBrowseKind::DATABASES => throw new \InvalidArgumentException('Site browse does not list databases.'),
                };

                Cache::put($cacheKey, [
                    'status' => 'ready',
                    ...$payload,
                ], now()->addMinutes(5));

                event(new SiteDatabaseBrowseReady(
                    serverId: (string) $server->getKey(),
                    organizationId: $this->organizationId,
                    siteId: $this->siteId,
                    kind: $this->kind->value,
                    table: $this->table,
                    page: $this->rowQuery?->page,
                    limit: $this->rowQuery?->limit ?? 50,
                    filters: $this->serializeFilters(),
                    status: 'ready',
                ));
            } finally {
                $connection->disconnect();
            }
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $message = is_string($message) ? $message : 'Unable to browse site database.';

            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => $message,
            ], now()->addMinutes(1));

            event(new SiteDatabaseBrowseReady(
                serverId: (string) ($site->server_id ?? ''),
                organizationId: $this->organizationId,
                siteId: $this->siteId,
                kind: $this->kind->value,
                table: $this->table,
                page: $this->rowQuery?->page,
                limit: $this->rowQuery?->limit ?? 50,
                filters: $this->serializeFilters(),
                status: 'failed',
                message: $message,
            ));
        } catch (\Throwable) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => 'Unable to browse site database.',
            ], now()->addMinutes(1));

            event(new SiteDatabaseBrowseReady(
                serverId: (string) ($site->server_id ?? ''),
                organizationId: $this->organizationId,
                siteId: $this->siteId,
                kind: $this->kind->value,
                table: $this->table,
                page: $this->rowQuery?->page,
                limit: $this->rowQuery?->limit ?? 50,
                filters: $this->serializeFilters(),
                status: 'failed',
                message: 'Unable to browse site database.',
            ));
        }
    }

    public static function cacheKey(
        string $siteId,
        DatabaseBrowseKind $kind,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
    ): string {
        return implode(':', array_filter([
            'site_db',
            $siteId,
            $kind->value,
            $table,
            $kind === DatabaseBrowseKind::ROWS ? $rowQuery?->fingerprint() : null,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function rowsPayload(
        ReadOnlyDatabaseClientInterface $databaseClient,
        \App\Packages\SSH\Contracts\SSHConnectionInterface $connection,
        \App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig $config,
        string $database,
        SqlIdentifierValidator $identifierValidator,
    ): array {
        $table = (string) $this->table;
        $identifierValidator->assertTableName($table);

        $query = $this->rowQuery ?? new DatabaseRowQuery();

        $result = $databaseClient->fetchRows($connection, $config, $database, $table, $query);

        return [
            'database' => $database,
            'engine' => $config->engine->value,
            'table' => $table,
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'rowCount' => count($result['rows']),
            'hasMore' => $result['hasMore'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
            'filters' => $this->serializeFilters(),
        ];
    }

    /**
     * @return list<array{column: string, operator: string, value: string|null}>
     */
    private function serializeFilters(): array
    {
        if ($this->rowQuery === null) {
            return [];
        }

        return array_map(
            static fn (DatabaseRowFilter $filter): array => $filter->toArray(),
            $this->rowQuery->filters,
        );
    }
}
