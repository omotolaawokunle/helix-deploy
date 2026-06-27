<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Contracts;

use App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ReadOnlyDatabaseClientInterface
{
    /**
     * @return list<string>
     */
    public function listDatabases(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config): array;

    /**
     * @return list<string>
     */
    public function listTables(SSHConnectionInterface $ssh, DatabaseConnectionConfig $config, string $database): array;

    /**
     * @return array{
     *     columns: list<string>,
     *     rows: list<list<string>>,
     *     hasMore: bool,
     *     page: int,
     *     limit: int,
     *     offset: int
     * }
     */
    public function fetchRows(
        SSHConnectionInterface $ssh,
        DatabaseConnectionConfig $config,
        string $database,
        string $table,
        DatabaseRowQuery $query,
    ): array;
}
