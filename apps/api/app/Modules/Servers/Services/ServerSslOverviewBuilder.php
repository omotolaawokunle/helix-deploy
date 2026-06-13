<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Carbon;

final class ServerSslOverviewBuilder
{
    private const int EXPIRING_SOON_DAYS = 30;

    /**
     * @return array{
     *     hasCertbot: bool,
     *     activeCertificateCount: int,
     *     expiringSoonCount: int,
     *     nearestExpiryAt: string|null,
     *     syncQueued: bool,
     *     certificates: list<array{
     *         siteId: string,
     *         domain: string,
     *         sslStatus: string,
     *         sslExpiresAt: string|null,
     *         sslCheckedAt: string|null,
     *         daysUntilExpiry: int|null
     *     }>
     * }
     */
    public function build(Server $server, bool $syncQueued = false): array
    {
        $sites = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->orderBy('domain')
            ->get();

        $threshold = now()->addDays(self::EXPIRING_SOON_DAYS);
        $activeSites = $sites->filter(
            fn (Site $site): bool => $site->enable_ssl && $site->ssl_status === SslStatus::ACTIVE,
        );

        $expiringSoonCount = $activeSites->filter(function (Site $site) use ($threshold): bool {
            if ($site->ssl_expires_at === null) {
                return false;
            }

            return $site->ssl_expires_at->lte($threshold);
        })->count();

        $nearestExpiry = $activeSites
            ->pluck('ssl_expires_at')
            ->filter()
            ->sort()
            ->first();

        $certificates = $sites->map(function (Site $site): array {
            $daysUntilExpiry = null;

            if ($site->ssl_expires_at instanceof Carbon) {
                $daysUntilExpiry = (int) now()->startOfDay()->diffInDays($site->ssl_expires_at->startOfDay(), false);
            }

            return [
                'siteId' => (string) $site->getKey(),
                'domain' => $site->domain,
                'sslStatus' => $site->ssl_status instanceof SslStatus
                    ? $site->ssl_status->value
                    : (string) $site->ssl_status,
                'sslExpiresAt' => $site->ssl_expires_at?->toIso8601String(),
                'sslCheckedAt' => $site->ssl_checked_at?->toIso8601String(),
                'daysUntilExpiry' => $daysUntilExpiry,
            ];
        })->values()->all();

        return [
            'hasCertbot' => $this->hasCertbot($server),
            'activeCertificateCount' => $activeSites->count(),
            'expiringSoonCount' => $expiringSoonCount,
            'nearestExpiryAt' => $nearestExpiry instanceof Carbon ? $nearestExpiry->toIso8601String() : null,
            'syncQueued' => $syncQueued,
            'certificates' => $certificates,
        ];
    }

    /**
     * @return array{activeCount: int, expiringSoonCount: int, nearestExpiryAt: string|null}|null
     */
    public function buildListSummary(Server $server): ?array
    {
        $activeCount = (int) ($server->active_ssl_count ?? 0);

        if ($activeCount === 0) {
            return null;
        }

        $expiringSoonCount = (int) ($server->expiring_ssl_count ?? 0);
        $nearestExpiry = $server->nearest_ssl_expiry ?? null;

        return [
            'activeCount' => $activeCount,
            'expiringSoonCount' => $expiringSoonCount,
            'nearestExpiryAt' => is_string($nearestExpiry) && $nearestExpiry !== ''
                ? Carbon::parse($nearestExpiry)->toIso8601String()
                : null,
        ];
    }

    public function shouldSync(Server $server): bool
    {
        $sites = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('enable_ssl', true)
            ->where('ssl_status', SslStatus::ACTIVE->value)
            ->get(['ssl_checked_at']);

        if ($sites->isEmpty()) {
            return false;
        }

        return $sites->contains(
            fn (Site $site): bool => $site->ssl_checked_at === null
                || $site->ssl_checked_at->lt(now()->subDay()),
        );
    }

    private function hasCertbot(Server $server): bool
    {
        $services = (array) ($server->installed_services ?? []);

        if (($services['certbot']['installed'] ?? false) === true) {
            return true;
        }

        return array_key_exists('certbot', $services);
    }
}
