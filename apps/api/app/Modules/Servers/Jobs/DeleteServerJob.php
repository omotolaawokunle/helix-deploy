<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Models\Organization as VaultOrganization;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteServerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $serverId,
        public readonly string $actorId,
    ) {
        $this->onQueue('monitoring');
    }

    public function handle(CredentialVault $credentialVault): void
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $beforeState = [
            'hostname' => $server->hostname,
            'ipAddress' => $server->ip_address,
            'status' => $server->status?->value,
        ];

        $credentialId = $server->credential_id;
        $organization = VaultOrganization::query()->find($server->organization_id);

        $server->delete();

        if ($credentialId !== null && $organization !== null) {
            $credentialVault->delete((string) $credentialId, $organization);
        }

        AuditLog::query()->create([
            'organization_id' => (string) $server->organization_id,
            'actor_id' => $this->actorId,
            'operation' => 'server.deleted',
            'resource_type' => Server::class,
            'resource_id' => $this->serverId,
            'before_state' => $beforeState,
            'after_state' => ['deleted' => true],
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => now(),
        ]);
    }
}
