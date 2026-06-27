<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser;

use App\Packages\DatabaseBrowser\Contracts\ReadOnlyDatabaseClientInterface;
use App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Validation\DatabaseRowWhereClauseBuilder;
use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use RuntimeException;

final class ReadOnlyDatabaseClient implements ReadOnlyDatabaseClientInterface
{
    public function __construct(
        private readonly SqlIdentifierValidator $identifierValidator,
        private readonly DatabaseRowWhereClauseBuilder $whereClauseBuilder,
    ) {
    }

    public function listDatabases(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config): array
    {
        return match ($config->engine) {
            DatabaseEngine::POSTGRESQL => $this->listPostgresqlDatabases($ssh, $config),
            DatabaseEngine::MYSQL => $this->listMysqlDatabases($ssh, $config),
        };
    }

    public function listTables(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config, string $database): array
    {
        $this->identifierValidator->assertDatabaseName($database);

        return match ($config->engine) {
            DatabaseEngine::POSTGRESQL => $this->listPostgresqlTables($ssh, $config, $database),
            DatabaseEngine::MYSQL => $this->listMysqlTables($ssh, $config, $database),
        };
    }

    public function fetchRows(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
        string $table,
        DatabaseRowQuery $query,
    ): array {
        $this->identifierValidator->assertDatabaseName($database);
        $this->identifierValidator->assertTableName($table);

        $limit = max(1, min($query->limit, 50));
        $page = max(1, $query->page);
        $offset = ($page - 1) * $limit;
        $boundedQuery = new DatabaseRowQuery(page: $page, limit: $limit, filters: $query->filters);

        $result = match ($config->engine) {
            DatabaseEngine::POSTGRESQL => $this->fetchPostgresqlRows($ssh, $config, $database, $table, $boundedQuery),
            DatabaseEngine::MYSQL => $this->fetchMysqlRows($ssh, $config, $database, $table, $boundedQuery),
        };

        $rows = $result['rows'];
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'columns' => $result['columns'],
            'rows' => $rows,
            'hasMore' => $hasMore,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @return list<string>
     */
    private function listPostgresqlDatabases(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config): array
    {
        $sql = "SELECT datname FROM pg_database WHERE datistemplate = false AND datallowconn ORDER BY datname";
        $command = $this->postgresqlCommand($config, 'postgres', $sql, true);
        $output = $this->runOrFail($ssh, $command);

        return $this->nonEmptyLines($output);
    }

    /**
     * @return list<string>
     */
    private function listPostgresqlTables(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
    ): array {
        $sql = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename";
        $command = $this->postgresqlCommand($config, $database, $sql, true);
        $output = $this->runOrFail($ssh, $command);

        return $this->nonEmptyLines($output);
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function fetchPostgresqlRows(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
        string $table,
        DatabaseRowQuery $query,
    ): array {
        $quotedTable = '"'.str_replace('"', '""', $table).'"';
        $where = $this->whereClauseBuilder->build(DatabaseEngine::POSTGRESQL, $query);
        $sql = sprintf(
            'SELECT * FROM %s%s ORDER BY 1 LIMIT %d OFFSET %d',
            $quotedTable,
            $where,
            $query->fetchLimit(),
            $query->offset(),
        );
        $command = $this->postgresqlCommand($config, $database, $sql, false);
        $output = $this->runOrFail($ssh, $command);

        return $this->parseTabularOutput($output);
    }

    /**
     * @return list<string>
     */
    private function listMysqlDatabases(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config): array
    {
        $command = $this->mysqlCommand($config, null, 'SHOW DATABASES', true);
        $output = $this->runMysqlOrFail($ssh, $command);

        return array_values(array_filter(
            $this->nonEmptyLines($output),
            static fn (string $name): bool => ! in_array($name, ['information_schema', 'mysql', 'performance_schema', 'sys'], true),
        ));
    }

    /**
     * @return list<string>
     */
    private function listMysqlTables(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
    ): array {
        $command = $this->mysqlCommand($config, $database, 'SHOW TABLES', true);
        $output = $this->runMysqlOrFail($ssh, $command);

        return $this->nonEmptyLines($output);
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function fetchMysqlRows(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
        string $table,
        DatabaseRowQuery $query,
    ): array {
        $quotedTable = '`'.str_replace('`', '``', $table).'`';
        $where = $this->whereClauseBuilder->build(DatabaseEngine::MYSQL, $query);
        $sql = sprintf(
            'SELECT * FROM %s%s ORDER BY 1 LIMIT %d OFFSET %d',
            $quotedTable,
            $where,
            $query->fetchLimit(),
            $query->offset(),
        );
        $command = $this->mysqlCommand($config, $database, $sql, false);
        $output = $this->runMysqlOrFail($ssh, $command);

        return $this->parseTabularOutput($output);
    }

    private function postgresqlCommand(
        DatabaseConnectionConfig $config,
        string $database,
        string $sql,
        bool $tuplesOnly,
    ): string {
        $flags = $tuplesOnly ? '-At' : '';

        return sprintf(
            'PGPASSWORD=%s psql -h %s -p %d -U %s -d %s %s -c %s',
            escapeshellarg($config->password),
            escapeshellarg($config->host),
            $config->port,
            escapeshellarg($config->username),
            escapeshellarg($database),
            $flags,
            escapeshellarg($sql),
        );
    }

    private function mysqlCommand(
        DatabaseConnectionConfig $config,
        ?string $database,
        string $sql,
        bool $skipHeaders,
    ): string {
        $databaseFlag = $database !== null ? sprintf('-D %s ', escapeshellarg($database)) : '';
        $headerFlag = $skipHeaders ? '-N ' : '';

        return sprintf(
            'MYSQL_PWD=%s mysql -h %s -P %d -u %s %s%s-B -e %s 2>/dev/null',
            escapeshellarg($config->password),
            escapeshellarg($config->host),
            $config->port,
            escapeshellarg($config->username),
            $databaseFlag,
            $headerFlag,
            escapeshellarg($sql),
        );
    }

    private function runMysqlOrFail(SSHConnectionInterface $ssh, string $command): string
    {
        $result = $ssh->run($command);

        if ($result->exitCode !== 0) {
            throw new RuntimeException('Database query failed.');
        }

        return $this->sanitizeMysqlCliOutput(trim($result->stdout));
    }

    private function sanitizeMysqlCliOutput(string $output): string
    {
        if ($output === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            if ($this->isMysqlCliNoiseLine($line)) {
                continue;
            }

            $filtered[] = $line;
        }

        return trim(implode("\n", $filtered));
    }

    private function isMysqlCliNoiseLine(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, 'mysql: [Warning]')
            || str_starts_with($trimmed, 'mysql: [Note]');
    }

    private function runOrFail(SSHConnectionInterface $ssh, string $command): string
    {
        $result = $ssh->run($command);

        if ($result->exitCode !== 0) {
            throw new RuntimeException('Database query failed.');
        }

        return trim($result->stdout);
    }

    /**
     * @return list<string>
     */
    private function nonEmptyLines(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $output) ?: [],
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function parseTabularOutput(string $output): array
    {
        $lines = $this->nonEmptyLines($output);

        if ($lines === []) {
            return ['columns' => [], 'rows' => []];
        }

        $columns = str_getcsv(array_shift($lines) ?? '', "\t", '"', '\\');
        $rows = [];

        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, "\t", '"', '\\');
        }

        return [
            'columns' => array_map(static fn (string $column): string => $column, $columns),
            'rows' => $rows,
        ];
    }
}
