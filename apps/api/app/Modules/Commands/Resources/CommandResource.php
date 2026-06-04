<?php

declare(strict_types=1);

namespace App\Modules\Commands\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Commands\Models\Command
 */
final class CommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'serverId' => (string) $this->server_id,
            'userId' => (string) $this->user_id,
            'command' => $this->command,
            'output' => $this->output,
            'exitCode' => $this->exit_code,
            'executedAt' => $this->executed_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => (string) $this->user?->getKey(),
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
        ];
    }
}
