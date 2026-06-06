<?php

declare(strict_types=1);

namespace App\Modules\Sites\Jobs;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Actions\EnvVarActions\SyncEnvVarsAction;
use App\Modules\Sites\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEnvVarsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public readonly string $siteId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(SyncEnvVarsAction $syncEnvVarsAction): void
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

        $syncEnvVarsAction->execute($site, $org);
    }
}
