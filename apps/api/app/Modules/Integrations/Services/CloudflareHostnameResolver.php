<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

final class CloudflareHostnameResolver
{
    public function isApex(string $hostname, string $baseDomain): bool
    {
        return strtolower($hostname) === strtolower($baseDomain);
    }

    public function belongsToZone(string $hostname, string $baseDomain): bool
    {
        $hostname = strtolower($hostname);
        $baseDomain = strtolower($baseDomain);

        return $hostname === $baseDomain || str_ends_with($hostname, '.'.$baseDomain);
    }

    public function recordName(string $hostname, string $baseDomain): string
    {
        if ($this->isApex($hostname, $baseDomain)) {
            return '@';
        }

        $suffix = '.'.$baseDomain;

        if (! str_ends_with(strtolower($hostname), $suffix)) {
            throw new \InvalidArgumentException(sprintf(
                'Hostname [%s] does not belong to zone [%s].',
                $hostname,
                $baseDomain,
            ));
        }

        return substr($hostname, 0, -strlen($suffix));
    }

    public function buildFromPrefix(string $prefix, string $baseDomain): string
    {
        $prefix = trim($prefix);

        if ($prefix === '' || $prefix === '@') {
            return $baseDomain;
        }

        return $prefix.'.'.$baseDomain;
    }
}
