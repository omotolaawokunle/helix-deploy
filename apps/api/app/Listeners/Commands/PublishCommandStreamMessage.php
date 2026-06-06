<?php

declare(strict_types=1);

namespace App\Listeners\Commands;

use App\Modules\Commands\Events\CommandCompleted;
use App\Modules\Commands\Events\CommandLogLine;
use App\Packages\Realtime\CommandStreamPublisher;

final class PublishCommandStreamMessage
{
    public function __construct(
        private readonly CommandStreamPublisher $publisher,
    ) {
    }

    public function handle(CommandLogLine|CommandCompleted $event): void
    {
        if ($event instanceof CommandLogLine) {
            $this->publisher->publish(
                (string) $event->command->getKey(),
                'log.line',
                $event->streamPayload(),
            );

            return;
        }

        $this->publisher->publish(
            (string) $event->command->getKey(),
            'command.completed',
            $event->streamPayload(),
        );
    }
}
