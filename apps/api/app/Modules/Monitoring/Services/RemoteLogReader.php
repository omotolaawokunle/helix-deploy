<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services;

use App\Modules\Monitoring\Contracts\RemoteLogReaderInterface;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use InvalidArgumentException;

final class RemoteLogReader implements RemoteLogReaderInterface
{
    public const int MAX_LINES = 500;

    public const int MIN_LINES = 10;

    private const string LARAVEL_LOG_GLOB = 'laravel*.log';

    /**
     * @return list<string>
     */
    public function tail(SSHConnectionInterface $ssh, string $absolutePath, int $lines): array
    {
        $lineCount = max(self::MIN_LINES, min($lines, self::MAX_LINES));

        $output = $ssh->run(sprintf(
            'tail -n %d %s 2>/dev/null || true',
            $lineCount,
            escapeshellarg($absolutePath),
        ))->output();

        return $this->parseLines($output);
    }

    /**
     * @return list<string>
     */
    public function tailLatestMatching(
        SSHConnectionInterface $ssh,
        string $directory,
        string $filenamePattern,
        int $lines,
    ): array {
        if ($filenamePattern !== self::LARAVEL_LOG_GLOB) {
            throw new InvalidArgumentException('Unsupported log filename pattern.');
        }

        $lineCount = max(self::MIN_LINES, min($lines, self::MAX_LINES));
        $escapedDirectory = escapeshellarg(rtrim($directory, '/'));

        $output = $ssh->run(sprintf(
            'dir=%s; latest=$(ls -1t "$dir"/laravel*.log "$dir"/laravel.log 2>/dev/null | head -1); if [ -n "$latest" ] && [ -f "$latest" ]; then tail -n %d "$latest"; fi',
            $escapedDirectory,
            $lineCount,
        ))->output();

        return $this->parseLines($output);
    }

    /**
     * @return list<string>
     */
    private function parseLines(string $output): array
    {
        $parsed = array_values(array_filter(
            explode("\n", $output),
            static fn (string $line): bool => $line !== '',
        ));

        return array_slice($parsed, -self::MAX_LINES);
    }
}
