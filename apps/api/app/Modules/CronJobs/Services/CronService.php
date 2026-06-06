<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CronJobs\Exceptions\InvalidCronExpressionException;
use App\Modules\CronJobs\Models\CronJob;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Cron\CronExpression;

class CronService
{
    public function validate(string $expression): void
    {
        if (! CronExpression::isValidExpression($expression)) {
            throw new InvalidCronExpressionException($expression);
        }
    }

    public function describe(string $expression): string
    {
        $this->validate($expression);

        return match (trim($expression)) {
            '0 0 * * *' => 'Every day at midnight',
            '0 * * * *' => 'Every hour',
            '*/5 * * * *' => 'Every 5 minutes',
            '0 0 * * 0' => 'Every week on Sunday at midnight',
            default => 'Scheduled as `'.$expression.'`',
        };
    }

    public function buildCrontab(Server $server): string
    {
        $jobs = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->orderBy('created_at')
            ->get();

        $lines = [
            '# Managed by HelixDeploy — do not edit manually',
            '# Last synced: '.now()->toIso8601String(),
        ];

        foreach ($jobs as $job) {
            if (! $job->active) {
                continue;
            }

            $lines[] = sprintf(
                '%s %s # helix:%s',
                $job->expression,
                $job->command,
                $job->getKey(),
            );
        }

        return implode("\n", $lines)."\n";
    }

    public function sync(Server $server, SSHConnectionInterface $ssh): void
    {
        $content = $this->buildCrontab($server);
        $activeCount = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->where('organization_id', (string) $server->organization_id)
            ->where('active', true)
            ->count();

        $user = $this->resolveCrontabUser($server);
        $escaped = str_replace("'", "'\\''", $content);
        $ssh->run(sprintf("printf '%%s\\n' '%s' | crontab -u %s -", $escaped, escapeshellarg($user)))->throw();

        $listed = $ssh->run(sprintf('crontab -l -u %s', escapeshellarg($user)))->output();

        if (trim($listed) !== trim($content)) {
            throw new \RuntimeException('Crontab verification failed after sync.');
        }

        AuditLog::record(
            operation: 'cron_jobs.synced',
            resource: $server,
            afterState: [
                'server_id' => (string) $server->getKey(),
                'active_count' => $activeCount,
            ],
        );
    }

    private function resolveCrontabUser(Server $server): string
    {
        $jobs = CronJob::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('server_id', (string) $server->getKey())
            ->first();

        return $jobs?->user ?? 'www-data';
    }
}
