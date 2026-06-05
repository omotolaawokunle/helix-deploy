<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Environment;
use Illuminate\Validation\ValidationException;

class DeleteEnvironmentAction
{
    public function execute(Environment $environment): void
    {
        if ($environment->servers()->exists() || $environment->sites()->exists()) {
            throw ValidationException::withMessages([
                'environment' => 'Remove all servers and sites from this environment before deleting it.',
            ]);
        }

        $beforeState = [
            'projectId' => (string) $environment->project_id,
            'name' => $environment->name,
            'isProduction' => $environment->is_production,
        ];

        AuditLog::record(
            operation: 'environment.deleted',
            resource: $environment,
            beforeState: $beforeState,
        );

        $environment->delete();
    }
}
