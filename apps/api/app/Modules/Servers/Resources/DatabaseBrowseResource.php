<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseBrowseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'status' => $payload['status'] ?? 'loading',
            'kind' => $payload['kind'] ?? null,
            'engine' => $payload['engine'] ?? null,
            'database' => $payload['database'] ?? null,
            'table' => $payload['table'] ?? null,
            'databases' => $payload['databases'] ?? [],
            'tables' => $payload['tables'] ?? [],
            'columns' => $payload['columns'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'rowCount' => $payload['rowCount'] ?? 0,
            'hasMore' => $payload['hasMore'] ?? false,
            'page' => $payload['page'] ?? 1,
            'offset' => $payload['offset'] ?? 0,
            'limit' => $payload['limit'] ?? null,
            'filters' => $payload['filters'] ?? [],
            'message' => $payload['message'] ?? null,
        ];
    }
}
