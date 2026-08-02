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
        return self::prepareEnv($composeFile, $preferredEnvFile)
            .self::runCompose($composeFile, $subcommand, $projectName);
    }

    /**
     * Compose V1 (docker-compose 1.29) crashes with KeyError: ContainerConfig when
     * recreating containers against newer Docker engines / rename-orphans left by a
     * previous failed recreate. Purge the project containers first, then up -d, and
     * retry once if ContainerConfig still appears.
     */
    public static function upCommand(
        string $composeFile,
        ?string $preferredEnvFile = null,
        ?string $projectName = null,
    ): string {
        $prepare = self::prepareEnv($composeFile, $preferredEnvFile);
        $purgeBody = self::purgeProjectContainersBody($projectName);
        $up = self::runCompose($composeFile, 'up -d', $projectName);

        $purgeFn = '';
        $purgeCall = '';
        if ($purgeBody !== '') {
            $purgeFn = 'helix_purge_compose_project() { '.$purgeBody.' }; ';
            $purgeCall = 'helix_purge_compose_project; ';
        }

        return $prepare
            .$purgeFn
            .$purgeCall
            .'up_log=$(mktemp); '
            .'set +e; '
            .'('.$up.') >"$up_log" 2>&1; up_ec=$?; '
            .'set -e; '
            .'cat "$up_log"; '
            .'if [ "$up_ec" -ne 0 ] && grep -q "ContainerConfig" "$up_log"; then '
            .$purgeCall
            .'set +e; '
            .'('.$up.') >"$up_log" 2>&1; up_ec=$?; '
            .'set -e; '
            .'cat "$up_log"; '
            .'fi; '
            .'rm -f "$up_log"; '
            .'exit "$up_ec"';
    }

    private static function prepareEnv(string $composeFile, ?string $preferredEnvFile): string
    {
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

        return $prepareEnv;
    }

    private static function runCompose(
        string $composeFile,
        string $subcommand,
        ?string $projectName,
    ): string {
        $file = escapeshellarg($composeFile);
        $composeDir = escapeshellarg(dirname($composeFile));

        $projectFlag = '';
        if (is_string($projectName) && trim($projectName) !== '') {
            $projectFlag = ' -p '.escapeshellarg(trim($projectName));
        }

        return 'cd '.$composeDir.' && '
            .'if docker compose version >/dev/null 2>&1; then'
            .' docker compose'.$projectFlag.' -f '.$file.' '.$subcommand.';'
            .' elif command -v docker-compose >/dev/null 2>&1; then'
            .' docker-compose'.$projectFlag.' -f '.$file.' '.$subcommand.';'
            .' else echo "Neither docker compose nor docker-compose is available on this server" >&2; exit 127;'
            .' fi';
    }

    private static function purgeProjectContainersBody(?string $projectName): string
    {
        if (! is_string($projectName) || trim($projectName) === '') {
            return '';
        }

        $project = trim($projectName);
        $labelFilter = escapeshellarg('label=com.docker.compose.project='.$project);
        $nameFilter = escapeshellarg('name='.$project);

        return 'ids=$(docker ps -aq --filter '.$labelFilter.' 2>/dev/null || true); '
            .'if [ -n "$ids" ]; then docker rm -f $ids >/dev/null 2>&1 || true; fi; '
            .'ids=$(docker ps -aq --filter '.$nameFilter.' 2>/dev/null || true); '
            .'if [ -n "$ids" ]; then docker rm -f $ids >/dev/null 2>&1 || true; fi';
    }
}
