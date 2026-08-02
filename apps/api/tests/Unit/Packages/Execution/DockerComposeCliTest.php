<?php

declare(strict_types=1);

use App\Packages\Execution\Steps\Docker\DockerComposeCli;

it('builds a compose build command without container purge', function (): void {
    $command = DockerComposeCli::command(
        '/var/www/app/releases/1/docker-compose.yml',
        'build',
        '/var/www/app/shared/.env',
        'myapp',
    );

    expect($command)
        ->toContain("docker compose -p 'myapp' -f '/var/www/app/releases/1/docker-compose.yml' build")
        ->toContain("docker-compose -p 'myapp' -f '/var/www/app/releases/1/docker-compose.yml' build")
        ->not->toContain('docker rm -f')
        ->not->toContain('com.docker.compose.project');
});

it('builds an up command that purges stale project containers before up -d', function (): void {
    $command = DockerComposeCli::upCommand(
        '/var/www/app/releases/1/docker-compose.yml',
        '/var/www/app/shared/.env',
        'bg-removernolbzdesigncom',
    );

    expect($command)
        ->toContain("label=com.docker.compose.project=bg-removernolbzdesigncom")
        ->toContain('name=bg-removernolbzdesigncom')
        ->toContain('docker rm -f')
        ->toContain("docker compose -p 'bg-removernolbzdesigncom' -f '/var/www/app/releases/1/docker-compose.yml' up -d")
        ->toContain("docker-compose -p 'bg-removernolbzdesigncom' -f '/var/www/app/releases/1/docker-compose.yml' up -d")
        ->toContain('ContainerConfig')
        ->toContain('/var/www/app/shared/.env');
});

it('up command still works without an explicit project name', function (): void {
    $command = DockerComposeCli::upCommand(
        '/var/www/app/releases/1/docker-compose.yml',
        '/var/www/app/shared/.env',
        null,
    );

    expect($command)
        ->toContain('up -d')
        ->toContain('docker compose')
        ->toContain('docker-compose')
        ->not->toContain('com.docker.compose.project');
});
