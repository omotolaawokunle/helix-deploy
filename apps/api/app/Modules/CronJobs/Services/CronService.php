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

            $user = $this->sanitizeCronUser((string) $job->user);

            // /etc/cron.d requires the username as the sixth field.
            $lines[] = sprintf(
                '%s %s %s # helix:%s',
                $job->expression,
                $user,
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

        $remotePath = $this->cronFilePath($server);
        $tmpPath = '/tmp/helix-cron-'.(string) $server->getKey();

        if (! $ssh->upload($content, $tmpPath)) {
            throw new \RuntimeException('Failed to upload crontab configuration.');
        }

        // deploy cannot use crontab -u; write /etc/cron.d via sudo (allowed in sudoers).
        $ssh->run(sprintf(
            'sudo cp %s %s && sudo chmod 644 %s && rm -f %s',
            escapeshellarg($tmpPath),
            escapeshellarg($remotePath),
            escapeshellarg($remotePath),
            escapeshellarg($tmpPath),
        ))->throw();

        $verifyTmp = $tmpPath.'.verify';
        $listed = $ssh->run(sprintf(
            'sudo cp %s %s && cat %s && rm -f %s',
            escapeshellarg($remotePath),
            escapeshellarg($verifyTmp),
            escapeshellarg($verifyTmp),
            escapeshellarg($verifyTmp),
        ))->throw()->output();

        if (trim($listed) !== trim($content)) {
            throw new \RuntimeException('Crontab verification failed after sync.');
        }

        AuditLog::record(
            operation: 'cron_jobs.synced',
            resource: $server,
            afterState: [
                'server_id' => (string) $server->getKey(),
                'active_count' => $activeCount,
                'cron_path' => $remotePath,
            ],
        );
    }

    public function cronFilePath(Server $server): string
    {
        // cron.d ignores filenames containing a dot — use hyphens only.
        $id = str_replace('.', '-', (string) $server->getKey());

        return '/etc/cron.d/helix-'.$id;
    }

    private function sanitizeCronUser(string $user): string
    {
        $user = trim($user);

        if ($user === '' || ! preg_match('/^[a-z_][a-z0-9_-]*$/i', $user)) {
            return 'www-data';
        }

        return $user;
    }
}
