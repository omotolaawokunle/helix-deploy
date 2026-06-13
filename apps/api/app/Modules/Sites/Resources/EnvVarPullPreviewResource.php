<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     status: string,
 *     diff?: array<string, mixed>|null,
 *     message?: string|null
 * } $resource
 */
class EnvVarPullPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $diff = $this->resource['diff'] ?? null;

        return [
            'status' => $this->resource['status'],
            'serverFileExists' => $diff['serverFileExists'] ?? false,
            'new' => $diff['new'] ?? [],
            'changed' => $diff['changed'] ?? [],
            'unchanged' => $diff['unchanged'] ?? [],
            'helixOnly' => $diff['helixOnly'] ?? [],
            'skipped' => $diff['skipped'] ?? [],
            'message' => $this->resource['message'] ?? null,
        ];
    }
}
