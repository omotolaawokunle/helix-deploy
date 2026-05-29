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

    private bool $connected = false;

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

        foreach ($this->responses as $pattern => $results) {
            if (! fnmatch($pattern, $command)) {
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
        return true;
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

    public function assertCommandExecuted(string $pattern): void
    {
        $matched = false;

        foreach ($this->executedCommands as $command) {
            if (fnmatch($pattern, $command)) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue($matched, "Expected command pattern [{$pattern}] to be executed.");
    }

    public function assertCommandNotExecuted(string $pattern): void
    {
        $matched = false;

        foreach ($this->executedCommands as $command) {
            if (fnmatch($pattern, $command)) {
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
}
