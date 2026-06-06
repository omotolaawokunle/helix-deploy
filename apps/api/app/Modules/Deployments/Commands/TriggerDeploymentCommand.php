<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Commands;

use App\Models\User;
use App\Modules\Deployments\Actions\TriggerDeploymentAction;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class TriggerDeploymentCommand extends Command
{
    protected $signature = 'deploy:trigger
        {site_id : Site UUID}
        {--branch= : Git branch to deploy}
        {--environment= : Optional environment name filter}';

    protected $description = 'Trigger a deployment for a site (same queue dispatch as the API).';

    public function handle(TriggerDeploymentAction $triggerDeploymentAction): int
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with(['server', 'environment'])
            ->whereKey((string) $this->argument('site_id'))
            ->first();

        if ($site === null) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        $environmentName = $this->option('environment');

        if (is_string($environmentName) && $environmentName !== '') {
            $siteEnvironmentName = $site->environment?->name;

            if ($siteEnvironmentName !== $environmentName) {
                $this->error(sprintf(
                    'Site environment is "%s", not "%s".',
                    (string) $siteEnvironmentName,
                    $environmentName,
                ));

                return self::FAILURE;
            }
        }

        $actor = $this->resolveActor((string) $site->organization_id);

        if ($actor === null) {
            $this->error('No organization owner found to attribute this deployment.');

            return self::FAILURE;
        }

        try {
            $deployment = $triggerDeploymentAction->execute(
                site: $site,
                actor: $actor,
                dto: new TriggerDeploymentDTO(
                    branch: $this->option('branch') !== null && $this->option('branch') !== ''
                        ? (string) $this->option('branch')
                        : null,
                ),
            );
        } catch (ConcurrentDeploymentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Deployment %s queued on deployments queue.', (string) $deployment->getKey()));
        $this->line('Job: '.RunDeploymentJob::class);

        return self::SUCCESS;
    }

    private function resolveActor(string $organizationId): ?User
    {
        return User::query()
            ->whereHas('organizations', function ($query) use ($organizationId): void {
                $query->where('organizations.id', $organizationId)
                    ->where('organization_users.role', TeamRole::OWNER->value);
            })
            ->orderBy('created_at')
            ->first();
    }
}
