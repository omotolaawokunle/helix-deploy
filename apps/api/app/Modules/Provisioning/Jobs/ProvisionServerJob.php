<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Jobs;

use App\Models\Organization;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Provisioning\Events\DeploymentLogLine;
use App\Modules\Provisioning\Events\ProvisioningCompleted;
use App\Modules\Provisioning\Models\ProvisioningLog;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;
use App\Packages\Provisioning\Contracts\ProvisioningScriptInterface;
use App\Packages\Provisioning\ProvisioningOrchestrator;
use App\Packages\Provisioning\Scripts\CreateDeployUser;
use App\Packages\Provisioning\Scripts\InstallCertbot;
use App\Packages\Provisioning\Scripts\InstallDocker;
use App\Packages\Provisioning\Scripts\InstallMySQL;
use App\Packages\Provisioning\Scripts\InstallNginx;
use App\Packages\Provisioning\Scripts\InstallNodejs;
use App\Packages\Provisioning\Scripts\InstallPHP;
use App\Packages\Provisioning\Scripts\InstallPostgreSQL;
use App\Packages\Provisioning\Scripts\InstallPython;
use App\Packages\Provisioning\Scripts\InstallRedis;
use App\Packages\Provisioning\Scripts\InstallSupervisor;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;

class ProvisionServerJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param array<int, string> $scripts
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $actorId,
        public readonly string $runId,
        public readonly array $scripts,
        public readonly array $options,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(
        ProvisioningOrchestrator $orchestrator,
        CredentialVaultInterface $credentialVault,
    ): void {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->firstOrFail();

        $organization = Organization::query()->findOrFail((string) $server->organization_id);
        $scriptInstances = $this->buildScripts($organization, $credentialVault);

        $lineCallback = function (string $line) use ($server): void {
            event(new DeploymentLogLine($server, $this->runId, $line));

            ProvisioningLog::query()->create([
                'server_id' => (string) $server->getKey(),
                'organization_id' => (string) $server->organization_id,
                'run_id' => $this->runId,
                'line' => $line,
                'created_at' => now(),
            ]);
        };

        $orchestrator->run(
            server: $server,
            scripts: $scriptInstances,
            lineCallback: $lineCallback,
            org: $organization,
        );

        $server->refresh();
        event(new ProvisioningCompleted($server, $this->runId));

        AuditLog::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'actor_id' => $this->actorId,
            'operation' => 'server.provisioned',
            'resource_type' => Server::class,
            'resource_id' => (string) $server->getKey(),
            'before_state' => null,
            'after_state' => [
                'runId' => $this->runId,
                'scripts' => $this->scripts,
                'installedServices' => (array) $server->installed_services,
            ],
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<int, ProvisioningScriptInterface>
     */
    private function buildScripts(Organization $organization, CredentialVaultInterface $credentialVault): array
    {
        return array_map(function (string $script) use ($organization, $credentialVault): ProvisioningScriptInterface {
            return match ($script) {
                'create-deploy-user' => new CreateDeployUser($credentialVault, $organization),
                'nginx' => new InstallNginx(),
                'php' => new InstallPHP(isset($this->options['phpVersion']) ? PhpVersion::from((string) $this->options['phpVersion']) : PhpVersion::V8_3),
                'mysql' => new InstallMySQL($credentialVault, $organization),
                'postgresql' => new InstallPostgreSQL($credentialVault, $organization),
                'redis' => new InstallRedis($credentialVault, $organization, isset($this->options['redisPassword']) ? (string) $this->options['redisPassword'] : null),
                'nodejs' => new InstallNodejs(isset($this->options['nodeVersion']) ? NodejsVersion::from((int) $this->options['nodeVersion']) : NodejsVersion::V20),
                'python' => new InstallPython(),
                'supervisor' => new InstallSupervisor(),
                'docker' => new InstallDocker(),
                'certbot' => new InstallCertbot(),
                default => throw new InvalidArgumentException(sprintf('Unknown provisioning script [%s].', $script)),
            };
        }, $this->scripts);
    }
}
