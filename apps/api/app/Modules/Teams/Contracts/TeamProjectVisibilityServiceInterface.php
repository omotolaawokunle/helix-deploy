<?php

declare(strict_types=1);

namespace App\Modules\Teams\Contracts;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;

/**
 * Team-scoped resources: project listings, project detail, server listings, server detail.
 * Org-scoped (unaffected by team membership): organizations, org members, teams CRUD,
 * deployments index at org level, audit logs, credentials, and mutations gated by org role.
 *
 * Rules:
 * - Org owners/admins see all projects and servers.
 * - Org members with no team assignments see all projects and servers.
 * - Team members on unrestricted teams (no linked projects) see all projects and servers.
 * - Team members on scoped teams see only linked projects and servers assigned to them.
 * - Servers without a project_id are hidden from scoped team members.
 */
interface TeamProjectVisibilityServiceInterface
{
    /**
     * @return list<string>|null Null when the user can see all projects in the organization.
     */
    public function visibleProjectIds(User $user, Organization $org): ?array;

    public function canAccessProject(User $user, Project $project): bool;
}
