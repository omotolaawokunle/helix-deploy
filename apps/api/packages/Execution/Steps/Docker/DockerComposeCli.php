<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

/**
 * Builds a shell snippet that prefers Compose V2 (`docker compose`) and falls
 * back to Compose V1 (`docker-compose`) on hosts that only have the hyphenated binary.
 */
final class DockerComposeCli
{
    public static function command(string $composeFile, string $subcommand): string
    {
        $file = escapeshellarg($composeFile);

        return 'if docker compose version >/dev/null 2>&1; then'
            .' docker compose -f '.$file.' '.$subcommand.';'
            .' elif command -v docker-compose >/dev/null 2>&1; then'
            .' docker-compose -f '.$file.' '.$subcommand.';'
            .' else echo "Neither docker compose nor docker-compose is available on this server" >&2; exit 127;'
            .' fi';
    }
}
