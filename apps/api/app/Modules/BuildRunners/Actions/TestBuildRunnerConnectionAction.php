<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Jobs\VerifyBuildRunnerConnectionJob;
use App\Modules\BuildRunners\Models\BuildRunner;

final class TestBuildRunnerConnectionAction
{
    public function execute(BuildRunner $runner, User $actor): void
    {
        $beforeState = [
            'status' => $runner->status?->value,
        ];

        $runner->forceFill([
            'status' => BuildRunnerStatus::CONNECTING->value,
        ])->save();

        AuditLog::record(
            operation: 'build_runner.connection_test_requested',
            resource: $runner,
            beforeState: $beforeState,
            afterState: [
                'status' => BuildRunnerStatus::CONNECTING->value,
                'requestedBy' => (string) $actor->getKey(),
            ],
        );

        VerifyBuildRunnerConnectionJob::dispatch((string) $runner->getKey());
    }
}
