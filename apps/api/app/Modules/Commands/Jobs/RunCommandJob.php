<?php

declare(strict_types=1);

namespace App\Modules\Commands\Jobs;

use App\Modules\Commands\Models\Command;
use App\Modules\Commands\Services\CommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $commandId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(CommandService $commandService): void
    {
        $command = Command::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->commandId)
            ->first();

        if ($command === null) {
            return;
        }

        $commandService->execute($command);
    }
}
