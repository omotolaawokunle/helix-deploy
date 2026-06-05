<?php

declare(strict_types=1);

namespace App\Modules\Commands\Events;

use App\Modules\Commands\Models\Command;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommandCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Command $command,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function streamPayload(): array
    {
        return [
            'commandId' => (string) $this->command->getKey(),
            'status' => $this->command->status->value,
            'exitCode' => $this->command->exit_code,
            'duration' => $this->command->duration(),
        ];
    }
}
