<?php

declare(strict_types=1);

namespace App\Modules\Commands\Events;

use App\Modules\Commands\Models\Command;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommandLogLine
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Command $command,
        public readonly string $line,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function streamPayload(): array
    {
        return [
            'commandId' => (string) $this->command->getKey(),
            'line' => $this->line,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
