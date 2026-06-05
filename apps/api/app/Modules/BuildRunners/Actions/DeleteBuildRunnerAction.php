<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Services\RunnerSlotManager;
use Illuminate\Validation\ValidationException;

final class DeleteBuildRunnerAction
{
    public function __construct(
        private readonly RunnerSlotManager $slotManager,
    ) {
    }

    public function execute(BuildRunner $runner, User $actor): void
    {
        if ($this->slotManager->hasActiveSlots($runner)) {
            throw ValidationException::withMessages([
                'runner' => ['Cannot delete a build runner while it has active build slots.'],
            ]);
        }

        AuditLog::record(
            operation: 'build_runner.deleted',
            resource: $runner,
            beforeState: [
                'name' => $runner->name,
                'ipAddress' => $runner->ip_address,
                'status' => $runner->status?->value,
            ],
            afterState: [
                'deletedBy' => (string) $actor->getKey(),
            ],
        );

        $runner->delete();
    }
}
