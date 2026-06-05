<?php

declare(strict_types=1);

namespace App\Modules\Commands\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Enums\CommandStatus;
use App\Modules\Commands\Models\Command;
use App\Modules\Commands\Services\CommandCancellationService;
use InvalidArgumentException;

final class CancelCommandAction
{
    public function __construct(
        private readonly CommandCancellationService $cancellationService,
    ) {}

    public function execute(Command $command, User $actor): Command
    {
        if (! in_array($command->status, [CommandStatus::PENDING, CommandStatus::RUNNING], true)) {
            throw new InvalidArgumentException('Only pending or running commands can be cancelled.');
        }

        $beforeState = [
            'status' => $command->status->value,
        ];

        $this->cancellationService->request((string) $command->getKey());

        if ($command->status === CommandStatus::PENDING) {
            $command->forceFill([
                'status' => CommandStatus::CANCELLED,
                'finished_at' => now(),
            ])->save();
        }

        AuditLog::record(
            operation: 'command.cancelled',
            resource: $command,
            beforeState: $beforeState,
            afterState: [
                'status' => $command->refresh()->status->value,
                'cancelledBy' => (string) $actor->getKey(),
            ],
        );

        return $command;
    }
}
