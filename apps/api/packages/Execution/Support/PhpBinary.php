<?php

declare(strict_types=1);

namespace App\Packages\Execution\Support;

use App\Modules\Sites\Models\Site;

final class PhpBinary
{
    public const DEFAULT_VERSION = '8.3';

    public static function forVersion(?string $version): string
    {
        $normalized = is_string($version) && $version !== '' ? $version : self::DEFAULT_VERSION;

        return 'php'.$normalized;
    }

    public static function forSite(Site $site): string
    {
        return self::forVersion($site->php_version);
    }

    public static function composerInstall(Site $site): string
    {
        return self::forSite($site).' /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction';
    }
}
