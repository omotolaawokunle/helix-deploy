<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Events\ServerFingerprintMismatch;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use Illuminate\Support\Facades\Log;

final class ReportFingerprintMismatchAction
{
    public function execute(SSHFingerprintMismatchException $exception): void
    {
        $server = $exception->server;

        Log::critical('SSH fingerprint mismatch', [
            'server_id' => (string) $server->getKey(),
            'expected' => $exception->expectedFingerprint,
            'received' => $exception->receivedFingerprint,
        ]);

        if ($server->status !== ServerStatus::DISCONNECTED) {
            $server->forceFill([
                'status' => ServerStatus::DISCONNECTED->value,
            ])->save();
        }

        InfrastructureEvent::query()->create([
            'organization_id' => (string) $server->organization_id,
            'server_id' => (string) $server->getKey(),
            'event_type' => 'server.fingerprint_mismatch',
            'payload' => [
                'expected' => $exception->expectedFingerprint,
                'received' => $exception->receivedFingerprint,
            ],
            'created_at' => now(),
        ]);

        event(new ServerFingerprintMismatch(
            server: $server->refresh(),
            expectedFingerprint: $exception->expectedFingerprint,
            receivedFingerprint: $exception->receivedFingerprint,
        ));
    }
}
