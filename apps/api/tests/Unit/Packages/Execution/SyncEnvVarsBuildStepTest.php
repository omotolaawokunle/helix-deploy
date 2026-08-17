<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Steps\Build\SyncEnvVarsBuildStep;
use Illuminate\Support\Str;

it('uploads env vars into the runner build directory before assets compile', function (): void {
    [$organization, , $site, $deployment] = executionFixture();
    $organization->users()->attach($deployment->triggered_by, ['role' => 'owner']);
    $owner = User::query()->findOrFail($deployment->triggered_by);
    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'env-build-runner-'.Str::random(4),
        'ip_address' => '10.0.0.81',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);

    $ssh = fakeSsh();
    queueSshResponses($ssh, ['chmod 600 *' => sshSuccess()]);

    app(CredentialVaultInterface::class)->storeSecret($organization, $site, 'VITE_API_URL', 'https://api.example.test');

    $ctx = BuildContext::forDeployment($deployment, $site, $runner, $ssh);

    (new SyncEnvVarsBuildStep)->run($ctx);

    $remotePath = rtrim($ctx->buildPath, '/').'/.env';
    $uploads = $ssh->getUploads();

    expect($uploads)->toHaveKey($remotePath)
        ->and($uploads[$remotePath])->toBe("VITE_API_URL=\"https://api.example.test\"\n")
        ->and($uploads)->not->toHaveKey('/var/www/'.$site->domain.'/shared/.env');

    $ssh->assertCommandExecuted('chmod 600 *');
    $ssh->assertCommandNotExecuted('sudo chown *');
});
