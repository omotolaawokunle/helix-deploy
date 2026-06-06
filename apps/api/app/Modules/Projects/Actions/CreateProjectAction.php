<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\DTOs\CreateProjectDTO;
use App\Modules\Projects\Models\Project;

class CreateProjectAction
{
    public function execute(Organization $org, CreateProjectDTO $dto): Project
    {
        $project = Project::query()->create([
            'organization_id' => (string) $org->getKey(),
            'name' => $dto->name,
            'description' => $dto->description,
        ]);

        AuditLog::record(
            operation: 'project.created',
            resource: $project,
            afterState: [
                'name' => $project->name,
                'description' => $project->description,
            ],
        );

        return $project;
    }
}
