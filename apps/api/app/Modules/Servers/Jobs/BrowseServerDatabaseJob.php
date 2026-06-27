<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Events\ServerDatabaseBrowseReady;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Services\ServerDatabaseConnectionResolver;
use App\Packages\DatabaseBrowser\Contracts\ReadOnlyDatabaseClientInterface;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;
use App\Packages\SSH\SSHManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class BrowseServerDatabaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly DatabaseEngine $engine,
        public readonly DatabaseBrowseKind $kind,
        public readonly ?string $database,
        public readonly ?string $table,
        public readonly ?DatabaseRowQuery $rowQuery,
    ) {
        $this->onQueue('commands');
    }

    public function handle(
        SSHManager $sshManager,
        CredentialVault $credentialVault,
        ServerDatabaseConnectionResolver $connectionResolver,
        ReadOnlyDatabaseClientInterface $databaseClient,
        SqlIdentifierValidator $identifierValidator,
    ): void {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $org = $server->organization;

        if ($org === null || (string) $org->getKey() !== $this->organizationId) {
            return;
        }

        $cacheKey = self::cacheKey(
            $this->serverId,
            $this->engine,
            $this->kind,
            $this->database,
            $this->table,
            $this->rowQuery,
        );

        try {
            $config = $connectionResolver->resolve($server, $org, $this->engine);
            $connection = $sshManager->connect($server, $credentialVault)->connect();

            try {
                $payload = match ($this->kind) {
                    DatabaseBrowseKind::DATABASES => [
                        'databases' => $databaseClient->listDatabases($connection, $config),
                    ],
                    DatabaseBrowseKind::TABLES => [
                        'database' => (string) $this->database,
                        'tables' => $databaseClient->listTables(
                            $connection,
                            $config,
                            (string) $this->database,
                        ),
                    ],
                    DatabaseBrowseKind::ROWS => $this->rowsPayload(
                        $databaseClient,
                        $connection,
                        $config,
                        $identifierValidator,
                    ),
                };

                Cache::put($cacheKey, [
                    'status' => 'ready',
                    ...$payload,
                ], now()->addMinutes(5));

                event(new ServerDatabaseBrowseReady(
                    serverId: $this->serverId,
                    organizationId: $this->organizationId,
                    engine: $this->engine->value,
                    kind: $this->kind->value,
                    database: $this->database,
                    table: $this->table,
                    limit: $this->rowQuery?->limit ?? 50,
                    page: $this->rowQuery?->page,
                    filters: $this->serializeFilters(),
                    status: 'ready',
                ));
            } finally {
                $connection->disconnect();
            }
        } catch (\Throwable $th) {
            report($th);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => 'Unable to browse database.',
            ], now()->addMinutes(1));

            event(new ServerDatabaseBrowseReady(
                serverId: $this->serverId,
                organizationId: $this->organizationId,
                engine: $this->engine->value,
                kind: $this->kind->value,
                database: $this->database,
                table: $this->table,
                limit: $this->rowQuery?->limit ?? 50,
                page: $this->rowQuery?->page,
                filters: $this->serializeFilters(),
                status: 'failed',
                message: 'Unable to browse database.',
            ));
        }
    }

    public static function cacheKey(
        string $serverId,
        DatabaseEngine $engine,
        DatabaseBrowseKind $kind,
        ?string $database,
        ?string $table,
        ?DatabaseRowQuery $rowQuery,
    ): string {
        return implode(':', array_filter([
            'server_db',
            $serverId,
            $engine->value,
            $kind->value,
            $database,
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
        SqlIdentifierValidator $identifierValidator,
    ): array {
        $database = (string) $this->database;
        $table = (string) $this->table;
        $identifierValidator->assertDatabaseName($database);
        $identifierValidator->assertTableName($table);

        $result = $databaseClient->fetchRows(
            $connection,
            $config,
            $database,
            $table,
            $this->rowQuery ?? new DatabaseRowQuery(),
        );

        return [
            'database' => $database,
            'table' => $table,
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'rowCount' => count($result['rows']),
            'hasMore' => $result['hasMore'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
            'filters' => array_map(
                static fn (DatabaseRowFilter $filter): array => $filter->toArray(),
                ($this->rowQuery ?? new DatabaseRowQuery())->filters,
            ),
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
