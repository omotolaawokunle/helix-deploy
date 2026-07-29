<?php

declare(strict_types=1);

use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use App\Modules\Sites\Services\SharedStorageBootstrap;

it('creates laravel storage skeleton with group-writable permissions', function (): void {
    $ssh = (new FakeSSHConnection())->connect();
    $ssh->addResponse('sudo mkdir -p *', new SSHResult('mkdir', 0, '', '', 0.01));
    $ssh->addResponse('sudo chown -R *', new SSHResult('chown', 0, '', '', 0.01));

    app(SharedStorageBootstrap::class)->ensureReady(
        $ssh,
        '/var/www/app.example.test/shared/storage',
        'deploy',
    );

    $ssh->assertCommandExecuted('sudo mkdir -p */shared/storage/app/public*');
    $ssh->assertCommandExecuted('*shared/storage/logs*');
    $ssh->assertCommandExecuted('*chown -R *deploy:www-data*shared/storage*');
    $ssh->assertCommandExecuted('*chmod g+s*');
});