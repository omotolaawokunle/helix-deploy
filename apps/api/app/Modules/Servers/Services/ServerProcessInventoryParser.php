<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Servers\DTOs\DiscoveredCronSnapshot;
use App\Modules\Servers\DTOs\DiscoveredDaemonSnapshot;
use Cron\CronExpression;

final class ServerProcessInventoryParser
{
    /**
     * @param list<string> $lines
     * @return array{daemons: list<DiscoveredDaemonSnapshot>, crons: list<DiscoveredCronSnapshot>}
     */
    public function parse(array $lines): array
    {
        $programs = [];
        $statusLines = [];
        $crons = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'DAEMON:')) {
                $program = $this->parseDaemon($line);

                if ($program !== null) {
                    $programs[] = $program;
                }

                continue;
            }

            if (str_starts_with($line, 'DSTAT:')) {
                $decoded = $this->decode(substr($line, 6));

                if ($decoded !== '') {
                    $statusLines[] = $decoded;
                }

                continue;
            }

            if (str_starts_with($line, 'CRON:')) {
                $cron = $this->parseUserCron($line);

                if ($cron !== null) {
                    $crons[] = $cron;
                }

                continue;
            }

            if (str_starts_with($line, 'CROND:')) {
                $cron = $this->parseSystemCron(substr($line, 6));

                if ($cron !== null) {
                    $crons[] = $cron;
                }
            }
        }

        $daemons = [];

        foreach ($programs as $program) {
            $daemons[] = new DiscoveredDaemonSnapshot(
                name: $program['name'],
                command: $program['command'],
                directory: $program['directory'],
                user: $program['user'],
                processes: $program['processes'],
                configPath: $program['configPath'],
                status: $this->statusFor($program['name'], $statusLines),
            );
        }

        return [
            'daemons' => $daemons,
            'crons' => $this->uniqueCrons($crons),
        ];
    }

    /**
     * @return array{name: string, command: string, directory: ?string, user: string, processes: int, configPath: string}|null
     */
    private function parseDaemon(string $line): ?array
    {
        $parts = explode('|', substr($line, 7));

        if (count($parts) !== 6) {
            return null;
        }

        $name = $this->decode($parts[0]);
        $command = $this->decode($parts[1]);
        $directory = $this->decode($parts[2]);
        $user = $this->decode($parts[3]);
        $processes = (int) $this->decode($parts[4]);
        $configPath = $this->decode($parts[5]);

        if ($name === '' || $command === '' || ! preg_match('/^[A-Za-z0-9_.:-]+$/', $name)) {
            return null;
        }

        if (! preg_match('/^[a-z_][a-z0-9_-]*$/i', $user)) {
            $user = 'www-data';
        }

        return [
            'name' => $name,
            'command' => $command,
            'directory' => $directory !== '' ? $directory : null,
            'user' => $user,
            'processes' => max(1, $processes),
            'configPath' => $configPath,
        ];
    }

    private function parseUserCron(string $line): ?DiscoveredCronSnapshot
    {
        $parts = explode('|', substr($line, 5), 2);

        if (count($parts) !== 2) {
            return null;
        }

        return $this->cronFromSchedule($this->decode($parts[1]), $this->decode($parts[0]), userCrontab: true);
    }

    private function parseSystemCron(string $payload): ?DiscoveredCronSnapshot
    {
        return $this->cronFromSchedule($this->decode($payload), null, userCrontab: false);
    }

    private function cronFromSchedule(string $line, ?string $forcedUser, bool $userCrontab): ?DiscoveredCronSnapshot
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || str_contains($line, '# helix:')) {
            return null;
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $line) === 1) {
            return null;
        }

        if (str_starts_with($line, '@')) {
            $limit = $userCrontab ? 2 : 3;
            $parts = preg_split('/\s+/', $line, $limit) ?: [];

            if (count($parts) !== $limit) {
                return null;
            }

            $user = $userCrontab ? (string) $forcedUser : $parts[1];
            $command = $userCrontab ? $parts[1] : $parts[2];

            return $this->cronSnapshot($parts[0], $command, $user);
        }

        $limit = $userCrontab ? 6 : 7;
        $parts = preg_split('/\s+/', $line, $limit) ?: [];

        if (count($parts) !== $limit) {
            return null;
        }

        $expression = implode(' ', array_slice($parts, 0, 5));
        $user = $userCrontab ? (string) $forcedUser : $parts[5];
        $command = $userCrontab ? $parts[5] : $parts[6];

        return $this->cronSnapshot($expression, $command, $user);
    }

    private function cronSnapshot(string $expression, string $command, string $user): ?DiscoveredCronSnapshot
    {
        $command = trim($command);
        $user = trim($user);

        if ($command === '' || strlen($command) > 4000 || ! CronExpression::isValidExpression($expression)) {
            return null;
        }

        if (! preg_match('/^[a-z_][a-z0-9_-]*$/i', $user)) {
            return null;
        }

        return new DiscoveredCronSnapshot($expression, $command, $user);
    }

    /**
     * @param list<string> $statusLines
     */
    private function statusFor(string $program, array $statusLines): string
    {
        $matched = [];

        foreach ($statusLines as $line) {
            if (preg_match('/^(\S+)/', trim($line), $matches) !== 1) {
                continue;
            }

            $token = $matches[1];
            $group = str_contains($token, ':') ? strstr($token, ':', true) : $token;

            if ($group === $program) {
                $matched[] = $line;
            }
        }

        $output = implode("\n", $matched);

        if (str_contains($output, 'FATAL') || str_contains($output, 'BACKOFF')) {
            return 'error';
        }

        if (str_contains($output, 'RUNNING') || str_contains($output, 'STARTING')) {
            return 'running';
        }

        return 'stopped';
    }

    /**
     * @param list<DiscoveredCronSnapshot> $crons
     * @return list<DiscoveredCronSnapshot>
     */
    private function uniqueCrons(array $crons): array
    {
        $unique = [];

        foreach ($crons as $cron) {
            $key = $cron->expression.'|'.$cron->user.'|'.$cron->command;
            $unique[$key] = $cron;
        }

        return array_values($unique);
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : '';
    }
}
