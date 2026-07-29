<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\DTOs\ClaimSiteDTO;
use App\Modules\Sites\DTOs\ClaimSiteResult;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use InvalidArgumentException;

final class ClaimSiteAction
{
    public function __construct(
        private readonly UpdateSiteAutoDeployAction $updateSiteAutoDeployAction,
    ) {
    }

    public function execute(Site $site, User $actor, ClaimSiteDTO $dto): ClaimSiteResult
    {
        if ($site->status !== SiteStatus::DISCOVERED) {
            throw new InvalidArgumentException('Site is not in discovered status.');
        }

        $site->repository_url = $dto->repositoryUrl;
        $site->repository_provider = $dto->repositoryProvider;
        $site->deploy_branch = $dto->deployBranch;

        $revealedWebhookSecret = null;

        if ($dto->autoDeployEnabled) {
            $autoDeployResult = $this->updateSiteAutoDeployAction->execute(
                site: $site,
                actor: $actor,
                enabled: true,
            );

            $site = $autoDeployResult->site;
            $revealedWebhookSecret = $autoDeployResult->revealedWebhookSecret;
        } else {
            $site->save();
        }

        $site->forceFill(['status' => SiteStatus::ACTIVE->value])->save();
        $site->refresh();

        AuditLog::record(
            operation: 'site.claimed',
            resource: $site,
            beforeState: ['status' => SiteStatus::DISCOVERED->value],
            afterState: [
                'status' => SiteStatus::ACTIVE->value,
                'repositoryUrl' => $dto->repositoryUrl,
                'repositoryProvider' => $dto->repositoryProvider,
                'deployBranch' => $dto->deployBranch,
                'autoDeployEnabled' => $dto->autoDeployEnabled,
            ],
        );

        return new ClaimSiteResult(
            site: $site,
            revealedWebhookSecret: $revealedWebhookSecret,
        );
    }
}
