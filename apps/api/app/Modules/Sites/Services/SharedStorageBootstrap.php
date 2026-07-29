<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Packages\SSH\Contracts\SSHConnectionInterface;

final class SharedStorageBootstrap
{
    /**
     * Laravel storage directories required for runtime (logs, cache, sessions, uploads).
     *
     * @var list<string>
     */
    private const SKELETON_DIRS = [
        'app/public',
        'framework/cache/data',
        'framework/sessions',
        'framework/views',
        'logs',
    ];

    public function ensureReady(SSHConnectionInterface $ssh, string $sharedStoragePath, string $owner): void
    {
        $mkdirArgs = implode(' ', array_map(
            static fn (string $relative): string => escapeshellarg($sharedStoragePath.'/'.$relative),
            self::SKELETON_DIRS,
        ));

        $ssh->run('sudo mkdir -p '.$mkdirArgs)->throw();

        $quotedPath = escapeshellarg($sharedStoragePath);
        $quotedOwner = escapeshellarg($owner.':www-data');

        $ssh->run(sprintf(
            'sudo chown -R %s %s && sudo chmod -R ug+rwX %s && sudo find %s -type d -exec chmod g+s {} +',
            $quotedOwner,
            $quotedPath,
            $quotedPath,
            $quotedPath,
        ))->throw();
    }
}
