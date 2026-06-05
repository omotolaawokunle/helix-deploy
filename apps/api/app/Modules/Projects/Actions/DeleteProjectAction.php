<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Project;
use Illuminate\Validation\ValidationException;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        if ($project->servers()->exists() || $project->sites()->exists()) {
            throw ValidationException::withMessages([
                'project' => 'Remove all servers and sites from this project before deleting it.',
            ]);
        }

        $beforeState = [
            'name' => $project->name,
            'description' => $project->description,
        ];

        AuditLog::record(
            operation: 'project.deleted',
            resource: $project,
            beforeState: $beforeState,
        );

        $project->delete();
    }
}
