<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Servers\Contracts\ServerInventoryIntrospectorInterface;
use App\Modules\Servers\DTOs\DiscoveredSiteSnapshot;
use App\Modules\Servers\DTOs\ServerInventorySnapshot;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class ServerInventoryIntrospector implements ServerInventoryIntrospectorInterface
{
    private const string START_MARKER = '__HELIX_INVENTORY__';

    private const string END_MARKER = '__HELIX_END__';

    /**
     * @var array<string, string>
     */
    private const array SERVICE_KEY_MAP = [
        'nginx' => 'nginx',
        'php' => 'php',
        'node' => 'nodejs',
        'mysql' => 'mysql',
        'mariadb' => 'mysql',
        'psql' => 'postgresql',
        'redis-cli' => 'redis',
        'supervisorctl' => 'supervisor',
        'docker' => 'docker',
    ];

    public function inspect(SSHConnectionInterface $connection): ServerInventorySnapshot
    {
        $result = $connection->run($this->inventoryScript())->throw();

        return $this->parseOutput($result->stdout);
    }

    public function parseOutput(string $output): ServerInventorySnapshot
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: [])));

        $startIndex = array_search(self::START_MARKER, $lines, true);
        $endIndex = array_search(self::END_MARKER, $lines, true);

        if ($startIndex === false || $endIndex === false || $endIndex <= $startIndex) {
            return new ServerInventorySnapshot(serviceKeys: [], sites: []);
        }

        $serviceKeys = [];
        $sites = [];

        for ($index = $startIndex + 1; $index < $endIndex; $index++) {
            $line = $lines[$index];

            if (str_starts_with($line, 'SVC:')) {
                $binary = substr($line, 4);
                $serviceKey = self::SERVICE_KEY_MAP[$binary] ?? null;

                if ($serviceKey !== null && ! in_array($serviceKey, $serviceKeys, true)) {
                    $serviceKeys[] = $serviceKey;
                }

                continue;
            }

            if (! str_starts_with($line, 'SITE:')) {
                continue;
            }

            $parts = explode('|', substr($line, 5), 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$domain, $webroot, $runtime] = $parts;
            $domain = strtolower(trim($domain));
            $webroot = trim($webroot);
            $runtime = strtolower(trim($runtime));

            if (! $this->isImportableDomain($domain)) {
                continue;
            }

            if ($webroot === '') {
                $webroot = '/var/www/'.$domain;
            }

            if (! in_array($runtime, ['php', 'nodejs', 'python', 'go', 'static', 'docker'], true)) {
                $runtime = 'static';
            }

            $sites[] = new DiscoveredSiteSnapshot(
                domain: $domain,
                webroot: $webroot,
                runtime: $runtime,
            );
        }

        return new ServerInventorySnapshot(
            serviceKeys: $serviceKeys,
            sites: $this->deduplicateSites($sites),
        );
    }

    private function inventoryScript(): string
    {
        return <<<'BASH'
export LC_ALL=C
echo "__HELIX_INVENTORY__"
for svc in nginx php node mysql mariadb psql redis-cli supervisorctl docker; do
  if command -v "$svc" >/dev/null 2>&1; then
    echo "SVC:$svc"
  fi
done
if [ -d /etc/nginx/sites-enabled ]; then
  for conf in /etc/nginx/sites-enabled/*; do
    [ -f "$conf" ] || continue
    base=$(basename "$conf")
    [ "$base" = "default" ] && continue
    server_name=$(grep -E '^\s*server_name\s+' "$conf" 2>/dev/null | head -1 | sed -E 's/^\s*server_name\s+//;s/;\s*$//' | awk '{print $1}')
    root_dir=$(grep -E '^\s*root\s+' "$conf" 2>/dev/null | head -1 | awk '{print $2}' | tr -d ';')
    has_php=$(grep -E 'fastcgi_pass|\.php' "$conf" 2>/dev/null | head -1)
    has_proxy=$(grep -E '^\s*proxy_pass\s+' "$conf" 2>/dev/null | head -1)
    runtime="static"
    if [ -n "$has_php" ]; then runtime="php"; elif [ -n "$has_proxy" ]; then runtime="nodejs"; fi
    domain="$server_name"
    [ -z "$domain" ] && domain="$base"
    [ -z "$root_dir" ] && root_dir="/var/www/$domain"
    echo "SITE:$domain|$root_dir|$runtime"
  done
fi
echo "__HELIX_END__"
BASH;
    }

    private function isImportableDomain(string $domain): bool
    {
        if ($domain === '' || $domain === '_' || $domain === 'default' || $domain === 'localhost') {
            return false;
        }

        if (str_starts_with($domain, '_')) {
            return false;
        }

        return preg_match('/^[a-z0-9.-]+$/', $domain) === 1;
    }

    /**
     * @param list<DiscoveredSiteSnapshot> $sites
     * @return list<DiscoveredSiteSnapshot>
     */
    private function deduplicateSites(array $sites): array
    {
        $unique = [];

        foreach ($sites as $site) {
            $unique[$site->domain] = $site;
        }

        return array_values($unique);
    }
}
