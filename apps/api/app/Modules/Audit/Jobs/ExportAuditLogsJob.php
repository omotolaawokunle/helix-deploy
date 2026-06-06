<?php

declare(strict_types=1);

namespace App\Modules\Audit\Jobs;

use App\Modules\Audit\Services\AuditLogCsvExporter;
use App\Modules\Audit\Services\AuditLogQueryService;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportAuditLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $exportId,
        public readonly array $filters,
    ) {
        $this->onQueue('default');
    }

    public function handle(AuditLogQueryService $queryService, AuditLogCsvExporter $exporter): void
    {
        $organization = Organization::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->organizationId)
            ->first();

        if ($organization === null) {
            return;
        }

        $request = Request::create('/', 'GET', $this->filters);
        $path = $exporter->exportToDisk(
            $queryService->exportQuery($organization, $request),
            "audit-exports/{$this->organizationId}/{$this->exportId}.csv",
        );

        Storage::disk('local')->put(
            "audit-exports/{$this->organizationId}/{$this->exportId}.meta.json",
            json_encode(['path' => $path, 'ready' => true], JSON_THROW_ON_ERROR),
        );
    }
}
