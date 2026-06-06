<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\DTOs\UpdateEnvironmentDTO;
use App\Modules\Projects\Models\Environment;

class UpdateEnvironmentAction
{
    public function execute(Environment $environment, UpdateEnvironmentDTO $dto): Environment
    {
        $beforeState = [
            'name' => $environment->name,
            'label' => $environment->label,
            'isProduction' => $environment->is_production,
        ];

        $environment->forceFill([
            'name' => $dto->name,
            'label' => $dto->label ?? $environment->label,
            'is_production' => $dto->isProduction,
        ])->save();

        AuditLog::record(
            operation: 'environment.updated',
            resource: $environment,
            beforeState: $beforeState,
            afterState: [
                'name' => $environment->name,
                'label' => $environment->label,
                'isProduction' => $environment->is_production,
            ],
        );

        return $environment->refresh();
    }
}
