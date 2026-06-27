<?php

declare(strict_types=1);

namespace App\Packages\Provisioning;

use App\Models\Organization;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Contracts\ProvisioningScriptInterface;
use App\Packages\Provisioning\Exceptions\ProvisioningStepFailedException;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisioningOrchestrator
{
    public function __construct(
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    /**
     * @param array<int, ProvisioningScriptInterface> $scripts
     * @param callable(string): void $lineCallback
     * @param array<string, mixed> $provisioningOptions
     */
    public function run(
        Server $server,
        array $scripts,
        callable $lineCallback,
        Organization $org,
        array $provisioningOptions = [],
    ): void {
        $connection = $this->sshManager->connectAndVerify($server, $this->credentialVault);
        $installedServices = [];
        $metadataBuilder = new ProvisioningInstalledServiceMetadata();
        $scriptOptions = array_merge($provisioningOptions, ['lineCallback' => $lineCallback]);

        try {
            foreach ($scripts as $script) {
                $startedAt = microtime(true);
                $lineCallback(sprintf('[%s] Starting...', $script->name()));

                try {
                    $script->handle($connection, $server, $scriptOptions);
                    $elapsed = (int) round(microtime(true) - $startedAt);
                    $lineCallback(sprintf('✓ %s complete (%ds)', $script->name(), $elapsed));
                    $installedServices[$script->name()] = array_merge(
                        $metadataBuilder->forScript($script->name(), $provisioningOptions),
                        ['idempotent' => $script->isIdempotent()],
                    );
                } catch (ProvisioningStepFailedException $exception) {
                    $lineCallback(sprintf('[%s] ERROR: %s', $script->name(), $exception->getMessage()));
                    Log::warning('Provisioning script failed', [
                        'server_id' => (string) $server->getKey(),
                        'script' => $script->name(),
                        'error' => $exception->getMessage(),
                    ]);

                    if ($script->name() === 'create-deploy-user') {
                        throw $exception;
                    }
                }
            }
        } finally {
            $connection->disconnect();
        }

        $server->forceFill([
            'installed_services' => array_replace((array) $server->installed_services, $installedServices),
        ])->save();
    }
}
