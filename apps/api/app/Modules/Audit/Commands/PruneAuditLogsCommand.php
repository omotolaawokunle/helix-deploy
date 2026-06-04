<?php

declare(strict_types=1);

namespace App\Modules\Audit\Commands;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune
        {--days=365 : Delete audit logs older than this many days}
        {--org= : Limit pruning to a single organization UUID}
        {--confirm : Required acknowledgement for destructive pruning}';

    protected $description = 'Prune audit logs older than the retention period (organization owners only).';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to prune audit logs. This operation is irreversible.');

            return self::FAILURE;
        }

        $actor = $this->resolveOwnerActor();

        if ($actor === null) {
            $this->error('Run this command as an authenticated organization owner (e.g. via artisan with a logged-in context) or ensure an owner exists.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $organizationId = $this->option('org');

        $query = DB::table('audit_logs')->where('created_at', '<', $cutoff);

        if (! $this->actorIsOwnerSomewhere($actor)) {
            $this->error('Only organization owners may prune audit logs.');

            return self::FAILURE;
        }

        if (is_string($organizationId) && $organizationId !== '') {
            if (! $this->actorIsOwnerOfOrganization($actor, $organizationId)) {
                $this->error('Only an organization owner may prune audit logs for that organization.');

                return self::FAILURE;
            }

            $query->where('organization_id', $organizationId);
        } else {
            $ownedOrganizationIds = $actor->organizations()
                ->wherePivot('role', TeamRole::OWNER->value)
                ->pluck('organizations.id')
                ->all();

            $query->whereIn('organization_id', $ownedOrganizationIds);
        }

        $deleted = $query->delete();

        $this->info(sprintf('Pruned %d audit log record(s) older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }

    private function resolveOwnerActor(): ?User
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user;
        }

        return User::query()
            ->whereHas('organizations', fn ($query) => $query->where('organization_users.role', TeamRole::OWNER->value))
            ->orderBy('created_at')
            ->first();
    }

    private function actorIsOwnerSomewhere(User $actor): bool
    {
        return Organization::query()
            ->whereHas('users', function ($query) use ($actor): void {
                $query->where('users.id', $actor->getKey())
                    ->where('organization_users.role', TeamRole::OWNER->value);
            })
            ->exists();
    }

    private function actorIsOwnerOfOrganization(User $actor, string $organizationId): bool
    {
        return Organization::query()
            ->whereKey($organizationId)
            ->whereHas('users', function ($query) use ($actor): void {
                $query->where('users.id', $actor->getKey())
                    ->where('organization_users.role', TeamRole::OWNER->value);
            })
            ->exists();
    }
}
