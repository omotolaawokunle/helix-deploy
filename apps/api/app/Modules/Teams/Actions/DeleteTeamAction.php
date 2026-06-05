<?php

declare(strict_types=1);

namespace App\Modules\Teams\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Teams\Models\Team;

class DeleteTeamAction
{
    public function execute(Team $team): void
    {
        $beforeState = [
            'name' => $team->name,
            'slug' => $team->slug,
        ];

        $team->delete();

        AuditLog::record(
            operation: 'team.deleted',
            resource: $team,
            beforeState: $beforeState,
            afterState: ['deleted' => true],
        );
    }
}
