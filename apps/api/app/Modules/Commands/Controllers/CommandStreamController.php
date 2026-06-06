<?php

declare(strict_types=1);

namespace App\Modules\Commands\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commands\Models\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CommandStreamController extends Controller
{
    public function stream(string $command): StreamedResponse
    {
        $commandModel = $this->resolveCommand($command);
        $this->authorize('view', $commandModel);

        return response()->stream(
            function () use ($commandModel): void {
                $this->emitCatchUp($commandModel);

                if ($commandModel->status->isTerminal()) {
                    $this->emitTerminalEvent($commandModel);

                    return;
                }

                $this->subscribeToLiveEvents((string) $commandModel->getKey());
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function emitCatchUp(Command $command): void
    {
        $output = (string) ($command->output ?? '');

        if ($output !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }

                $this->writeSseEvent('log.line', [
                    'commandId' => (string) $command->getKey(),
                    'line' => $line,
                    'timestamp' => $command->started_at?->toIso8601String()
                        ?? $command->executed_at?->toIso8601String()
                        ?? now()->toIso8601String(),
                ]);
            }
        }
    }

    private function subscribeToLiveEvents(string $commandId): void
    {
        $channel = 'command.'.$commandId;
        $client = Redis::connection()->client();

        if (! $client instanceof \Redis) {
            return;
        }

        $client->setOption(\Redis::OPT_READ_TIMEOUT, -1);

        $client->subscribe([$channel], function (\Redis $redis, string $chan, string $message) use ($channel): void {
            if (connection_aborted()) {
                $redis->unsubscribe([$channel]);

                return;
            }

            $payload = json_decode($message, true);
            if (! is_array($payload) || ! isset($payload['event'], $payload['data'])) {
                return;
            }

            $this->writeSseEvent((string) $payload['event'], (array) $payload['data']);

            if ($payload['event'] === 'command.completed') {
                $redis->unsubscribe([$channel]);
            }
        });
    }

    private function emitTerminalEvent(Command $command): void
    {
        $this->writeSseEvent('command.completed', [
            'commandId' => (string) $command->getKey(),
            'status' => $command->status->value,
            'exitCode' => $command->exit_code,
            'duration' => $command->duration(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeSseEvent(string $event, array $data): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function resolveCommand(string $commandId): Command
    {
        $command = Command::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($commandId)
            ->first();

        if ($command === null) {
            throw (new ModelNotFoundException())->setModel(Command::class, [$commandId]);
        }

        return $command;
    }
}
