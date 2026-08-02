<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Packages\SSH\Contracts\SSHConnectionInterface;
use PHPUnit\Framework\Assert;
use RuntimeException;

class FakeSSHConnection implements SSHConnectionInterface
{
    /**
     * @var array<string, list<SSHResult>>
     */
    private array $responses = [];

    /**
     * @var list<string>
     */
    private array $executedCommands = [];

    /**
     * @var list<?int>
     */
    private array $executedTimeouts = [];

    /**
     * @var array<string, string>
     */
    private array $uploads = [];

    private bool $connected = false;

    public bool $interrupted = false;

    public function connect(): static
    {
        $this->connected = true;

        return $this;
    }

    public function addResponse(string $pattern, SSHResult $result): static
    {
        return $this->addSequence($pattern, $result);
    }

    public function addSequence(string $pattern, SSHResult ...$results): static
    {
        $this->responses[$pattern] ??= [];
        array_push($this->responses[$pattern], ...$results);

        return $this;
    }

    public function run(string $command, ?callable $lineCallback = null, ?int $timeout = null): SSHResult
    {
        $this->executedCommands[] = $command;
        $this->executedTimeouts[] = $timeout;

        foreach ($this->responses as $pattern => $results) {
            if (! $this->commandMatches($pattern, $command)) {
                continue;
            }

            if ($results === []) {
                continue;
            }

            $result = array_shift($this->responses[$pattern]);

            if ($result === null) {
                break;
            }

            if ($lineCallback !== null) {
                $output = $result->stdout !== '' ? $result->stdout : $result->stderr;
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $lineCallback($line);
                }
            }

            return $result;
        }

        throw new RuntimeException("FakeSSHConnection: unmatched command: {$command}");
    }

    public function upload(string $content, string $remotePath): bool
    {
        $this->uploads[$remotePath] = $content;

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getUploads(): array
    {
        return $this->uploads;
    }

    public function interrupt(): void
    {
        $this->interrupted = true;
        $this->disconnect();
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return array<string>
     */
    public function getExecutedCommands(): array
    {
        return $this->executedCommands;
    }

    /**
     * @return list<?int>
     */
    public function getExecutedTimeouts(): array
    {
        return $this->executedTimeouts;
    }

    public function assertCommandExecuted(string $pattern): void
    {
        $matched = false;

        foreach ($this->executedCommands as $command) {
            if ($this->commandMatches($pattern, $command)) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue($matched, "Expected command pattern [{$pattern}] to be executed.");
    }

    public function assertCommandTimeout(string $pattern, int $timeout): void
    {
        foreach ($this->executedCommands as $index => $command) {
            if (! $this->commandMatches($pattern, $command)) {
                continue;
            }

            Assert::assertSame(
                $timeout,
                $this->executedTimeouts[$index] ?? null,
                "Expected command pattern [{$pattern}] to use timeout [{$timeout}].",
            );

            return;
        }

        Assert::fail("Expected command pattern [{$pattern}] to be executed.");
    }

    public function assertCommandNotExecuted(string $pattern): void
    {
        $matched = false;

        foreach ($this->executedCommands as $command) {
            if ($this->commandMatches($pattern, $command)) {
                $matched = true;
                break;
            }
        }

        Assert::assertFalse($matched, "Expected command pattern [{$pattern}] not to be executed.");
    }

    public function assertCommandCount(int $count): void
    {
        Assert::assertCount($count, $this->executedCommands);
    }

    private function commandMatches(string $pattern, string $command): bool
    {
        // PHP's fnmatch() rejects subjects longer than 1024 bytes.
        if (strlen($command) <= 1024 && strlen($pattern) <= 1024) {
            return fnmatch($pattern, $command);
        }

        $parts = explode('*', $pattern);
        $regex = '/^'.implode('.*', array_map(
            static fn (string $part): string => preg_quote($part, '/'),
            $parts,
        )).'$/s';

        return preg_match($regex, $command) === 1;
    }
}
