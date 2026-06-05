<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\DTOs\UpdateProjectDTO;
use App\Modules\Projects\Models\Project;

class UpdateProjectAction
{
    public function execute(Project $project, UpdateProjectDTO $dto): Project
    {
        $beforeState = [
            'name' => $project->name,
            'description' => $project->description,
        ];

        $project->forceFill([
            'name' => $dto->name,
            'description' => $dto->description,
        ])->save();

        AuditLog::record(
            operation: 'project.updated',
            resource: $project,
            beforeState: $beforeState,
            afterState: [
                'name' => $project->name,
                'description' => $project->description,
            ],
        );

        return $project->refresh();
    }
}
