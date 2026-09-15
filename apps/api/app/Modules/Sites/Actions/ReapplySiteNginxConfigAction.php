<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Sites\Contracts\NginxConfigGeneratorInterface;
use App\Modules\Sites\Models\Site;

final class ReapplySiteNginxConfigAction
{
    public function __construct(
        private readonly NginxConfigGeneratorInterface $nginxConfigGenerator,
        private readonly UpdateNginxConfigAction $updateNginxConfigAction,
    ) {
    }

    public function execute(Site $site, User $actor): Site
    {
        $config = $this->nginxConfigGenerator->generate($site);

        return $this->updateNginxConfigAction->execute($site, $actor, $config);
    }
}
