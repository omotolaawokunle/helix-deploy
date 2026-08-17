<?php

declare(strict_types=1);

namespace App\Packages\Execution\Support;

final class ArtisanMigrateShellCommand
{
    public static function forReleasePath(string $releasePath): string
    {
        return 'cd '.escapeshellarg($releasePath).' && php -r '.escapeshellarg(self::ensureDatabaseScript())
            .' && php artisan migrate --force --no-interaction';
    }

    /**
     * Laravel migrate --force will create a missing database when it can detect the
     * PDO error. PostgreSQL often surfaces SQLSTATE 7 / HY000 instead of 08006, so
     * the prompt never runs under --no-interaction. Create the DB ourselves first.
     */
    private static function ensureDatabaseScript(): string
    {
        return <<<'PHP'
if (! is_file('vendor/autoload.php') || ! is_file('bootstrap/app.php')) { exit(0); }
require 'vendor/autoload.php';
try {
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable) { exit(0); }
$default = config('database.default');
$conn = config('database.connections.'.$default);
$driver = is_array($conn) ? ($conn['driver'] ?? null) : null;
$database = is_array($conn) ? ($conn['database'] ?? null) : null;
if (! is_string($driver) || ! is_string($database) || $database === '' || $database === ':memory:') { exit(0); }
if ($driver === 'sqlite') {
    $dir = dirname($database);
    if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (! is_file($database)) { touch($database); fwrite(STDOUT, "Created missing SQLite database [{$database}].\n"); }
    exit(0);
}
try { Illuminate\Support\Facades\DB::connection($default)->getPdo(); exit(0); } catch (Throwable) {}
if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) { exit(0); }
config(['database.connections.'.$default.'.database' => $driver === 'pgsql' ? 'postgres' : null]);
Illuminate\Support\Facades\DB::purge($default);
try {
    $db = Illuminate\Support\Facades\DB::connection($default);
    if ($driver === 'pgsql') {
        if ($db->select('select 1 from pg_database where datname = ?', [$database]) === []) {
            $db->getPdo()->exec('create database "'.str_replace('"', '""', $database).'"');
            fwrite(STDOUT, "Created missing database [{$database}].\n");
        }
    } else {
        $db->getPdo()->exec('create database if not exists `'.str_replace('`', '``', $database).'`');
        fwrite(STDOUT, "Created missing database [{$database}].\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to create missing database: '.$e->getMessage()."\n");
    exit(1);
}
PHP;
    }
}
