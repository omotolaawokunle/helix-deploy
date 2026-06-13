<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{status: string, lines: list<string>, message?: string|null} $resource
 */
class ServerLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'lines' => $this->resource['lines'] ?? [],
            'message' => $this->resource['message'] ?? null,
            'logType' => $this->resource['logType'] ?? null,
            'linesRequested' => $this->resource['linesRequested'] ?? null,
        ];
    }
}
