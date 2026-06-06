<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\DTOs\CreateEnvironmentDTO;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;

class CreateEnvironmentAction
{
    public function execute(Project $project, CreateEnvironmentDTO $dto): Environment
    {
        $environment = Environment::query()->create([
            'project_id' => (string) $project->getKey(),
            'organization_id' => (string) $project->organization_id,
            'name' => $dto->name,
            'label' => $dto->label ?? $this->defaultLabel($dto->name),
            'is_production' => $dto->isProduction,
        ]);

        AuditLog::record(
            operation: 'environment.created',
            resource: $environment,
            afterState: [
                'projectId' => (string) $project->getKey(),
                'name' => $environment->name,
                'isProduction' => $environment->is_production,
            ],
        );

        return $environment;
    }

    private function defaultLabel(string $name): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $name));
    }
}
