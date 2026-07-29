<?php

declare(strict_types=1);

namespace App\Packages\Execution\Steps\Docker;

use App\Packages\Execution\DeploymentContext;

final class DockerComposePath
{
    public static function resolve(DeploymentContext $ctx): ?string
    {
        $configured = $ctx->site->docker_compose_path;

        if (! is_string($configured) || trim($configured) === '') {
            return null;
        }

        $configured = trim($configured);

        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        return rtrim($ctx->releasePath, '/').'/'.ltrim($configured, '/');
    }

    public static function resolveOrDefault(DeploymentContext $ctx): string
    {
        return self::resolve($ctx) ?? rtrim($ctx->sharedPath, '/').'/docker-compose.yml';
    }
}
