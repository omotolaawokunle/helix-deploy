<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Actions\EnvVarActions\ApplyEnvVarsPullAction;
use App\Modules\Sites\Enums\EnvVarPullStrategy;
use App\Modules\Sites\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApplyEnvVarsPullJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public readonly string $siteId,
        public readonly EnvVarPullStrategy $strategy,
    ) {
        $this->onQueue('commands');
    }

    public function handle(ApplyEnvVarsPullAction $applyEnvVarsPullAction): void
    {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->siteId)
            ->first();

        if ($site === null) {
            return;
        }

        $org = Organization::query()->find((string) $site->organization_id);

        if ($org === null) {
            return;
        }

        $applyEnvVarsPullAction->execute($site, $org, $this->strategy);
    }
}
