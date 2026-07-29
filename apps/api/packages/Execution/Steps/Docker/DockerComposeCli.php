<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

/**
 * Builds a shell snippet that prefers Compose V2 (`docker compose`) and falls
 * back to Compose V1 (`docker-compose`) on hosts that only have the hyphenated binary.
 *
 * Also ensures a `.env` exists next to the compose file — Compose fails hard when
 * services declare `env_file: .env` but the file is missing from a fresh release.
 *
 * A stable `-p` project name keeps redeploys on the same stack (containers/volumes)
 * instead of creating a competing project from the release directory name.
 */
final class DockerComposeCli
{
    public static function command(
        string $composeFile,
        string $subcommand,
        ?string $preferredEnvFile = null,
        ?string $projectName = null,
    ): string {
        $file = escapeshellarg($composeFile);
        $composeDir = escapeshellarg(dirname($composeFile));
        $composeEnv = escapeshellarg(dirname($composeFile).'/.env');

        $prepareEnv = 'if [ ! -f '.$composeEnv.' ]; then ';
        if (is_string($preferredEnvFile) && $preferredEnvFile !== '') {
            $preferred = escapeshellarg($preferredEnvFile);
            $prepareEnv .= 'if [ -f '.$preferred.' ]; then cp '.$preferred.' '.$composeEnv.'; '
                .'else touch '.$composeEnv.'; fi; ';
        } else {
            $prepareEnv .= 'touch '.$composeEnv.'; ';
        }
        $prepareEnv .= 'fi; ';

        $projectFlag = '';
        if (is_string($projectName) && trim($projectName) !== '') {
            $projectFlag = ' -p '.escapeshellarg(trim($projectName));
        }

        return $prepareEnv
            .'cd '.$composeDir.' && '
            .'if docker compose version >/dev/null 2>&1; then'
            .' docker compose'.$projectFlag.' -f '.$file.' '.$subcommand.';'
            .' elif command -v docker-compose >/dev/null 2>&1; then'
            .' docker-compose'.$projectFlag.' -f '.$file.' '.$subcommand.';'
            .' else echo "Neither docker compose nor docker-compose is available on this server" >&2; exit 127;'
            .' fi';
    }
}
