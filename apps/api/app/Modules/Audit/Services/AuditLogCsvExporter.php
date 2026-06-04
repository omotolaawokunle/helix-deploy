<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

final class AuditLogCsvExporter
{
    /**
     * @param Builder<AuditLog> $query
     */
    public function exportToDisk(Builder $query, string $path): string
    {
        $disk = Storage::disk('local');
        $fullPath = $disk->path($path);
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($fullPath, 'wb');
        abort_unless(is_resource($handle), 500, 'Unable to create export file.');

        fputcsv($handle, [
            'id',
            'operation',
            'actor_id',
            'actor_name',
            'actor_email',
            'resource_type',
            'resource_id',
            'ip_address',
            'request_id',
            'created_at',
        ]);

        $query->chunkById(500, function ($logs) use ($handle): void {
            foreach ($logs as $log) {
                /** @var AuditLog $log */
                fputcsv($handle, [
                    (string) $log->getKey(),
                    $log->operation,
                    $log->actor_id,
                    $log->actor?->name,
                    $log->actor?->email,
                    $log->resource_type,
                    $log->resource_id,
                    $log->ip_address,
                    $log->request_id,
                    $log->created_at?->toIso8601String(),
                ]);
            }
        }, column: 'id');

        fclose($handle);

        return $path;
    }

    /**
     * @param Builder<AuditLog> $query
     */
    public function stream(Builder $query): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            abort_unless(is_resource($handle), 500, 'Unable to open export stream.');

            fputcsv($handle, [
                'id',
                'operation',
                'actor_id',
                'actor_name',
                'actor_email',
                'resource_type',
                'resource_id',
                'ip_address',
                'request_id',
                'created_at',
            ]);

            $query->chunkById(500, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    /** @var AuditLog $log */
                    fputcsv($handle, [
                        (string) $log->getKey(),
                        $log->operation,
                        $log->actor_id,
                        $log->actor?->name,
                        $log->actor?->email,
                        $log->resource_type,
                        $log->resource_id,
                        $log->ip_address,
                        $log->request_id,
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            }, column: 'id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
