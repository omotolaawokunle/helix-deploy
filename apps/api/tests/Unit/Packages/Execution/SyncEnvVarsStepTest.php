<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Packages\Execution\Steps\Shared\SyncEnvVarsStep;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;

it('uploads env vars to shared path and writes audit log during deployment', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture();
    $organization->users()->attach($deployment->triggered_by, ['role' => 'owner']);
    $ssh = (new FakeSSHConnection())->connect();

    $ssh->addSequence('chmod 640 *', sshSuccess());
    $ssh->addSequence('chown deploy:www-data *', sshSuccess());

    $vault = app(CredentialVaultInterface::class);
    $vault->storeSecret($organization, $site, 'APP_KEY', 'secret-value');

    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new SyncEnvVarsStep())->run($ctx);

    $remotePath = '/var/www/'.$site->domain.'/shared/.env';
    $uploads = $ssh->getUploads();

    expect($uploads)->toHaveKey($remotePath)
        ->and($uploads[$remotePath])->toBe("APP_KEY=\"secret-value\"\n");

    expect(AuditLog::query()->where('operation', 'env_vars.synced')->exists())->toBeTrue();
});

it('uploads empty env file when site has no variables', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture();
    $ssh = (new FakeSSHConnection())->connect();

    $ssh->addSequence('chmod 640 *', sshSuccess());
    $ssh->addSequence('chown deploy:www-data *', sshSuccess());

    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new SyncEnvVarsStep())->run($ctx);

    $remotePath = '/var/www/'.$site->domain.'/shared/.env';

    expect($ssh->getUploads()[$remotePath])->toBe('');
});
