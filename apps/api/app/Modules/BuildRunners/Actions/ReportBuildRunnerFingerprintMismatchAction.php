<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Actions;

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Events\BuildRunnerFingerprintMismatch;
use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Packages\SSH\Exceptions\BuildRunnerSSHFingerprintMismatchException;
use Illuminate\Support\Facades\Log;

final class ReportBuildRunnerFingerprintMismatchAction
{
    public function execute(BuildRunnerSSHFingerprintMismatchException $exception): void
    {
        $runner = $exception->runner;

        Log::critical('Build runner SSH fingerprint mismatch', [
            'runner_id' => (string) $runner->getKey(),
            'expected' => $exception->expectedFingerprint,
            'received' => $exception->receivedFingerprint,
        ]);

        if ($runner->status !== BuildRunnerStatus::OFFLINE) {
            $runner->forceFill([
                'status' => BuildRunnerStatus::OFFLINE->value,
            ])->save();
        }

        InfrastructureEvent::query()->create([
            'organization_id' => (string) $runner->organization_id,
            'server_id' => null,
            'event_type' => 'build_runner.fingerprint_mismatch',
            'payload' => [
                'runnerId' => (string) $runner->getKey(),
                'expected' => $exception->expectedFingerprint,
                'received' => $exception->receivedFingerprint,
            ],
            'created_at' => now(),
        ]);

        event(new BuildRunnerFingerprintMismatch(
            runner: $runner->refresh(),
            expectedFingerprint: $exception->expectedFingerprint,
            receivedFingerprint: $exception->receivedFingerprint,
        ));
    }
}
