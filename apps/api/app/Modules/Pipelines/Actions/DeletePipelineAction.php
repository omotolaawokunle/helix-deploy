<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Pipelines\Models\Pipeline;
use Illuminate\Validation\ValidationException;

class DeletePipelineAction
{
    public function execute(Pipeline $pipeline): void
    {
        if ($pipeline->sites()->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => 'Unlink this pipeline from all sites before deleting it.',
            ]);
        }

        $beforeState = [
            'name' => $pipeline->name,
            'description' => $pipeline->description,
            'projectId' => $pipeline->project_id,
        ];

        AuditLog::record(
            operation: 'pipeline.deleted',
            resource: $pipeline,
            beforeState: $beforeState,
        );

        $pipeline->delete();
    }
}
