<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;
use App\Modules\BuildRunners\Services\InMemoryRunnerSlotStore;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Str;

function useInMemoryRunnerSlotStore(): InMemoryRunnerSlotStore
{
    $store = new InMemoryRunnerSlotStore();
    app()->instance(RunnerSlotStoreInterface::class, $store);

    return $store;
}

function createPoolTestRunner(
    ?Organization $organization = null,
    string $name = 'Pool Runner',
    string $ipAddress = '10.0.0.1',
    int $maxConcurrentBuilds = 1,
    array $supportedRuntimes = ['php'],
    ?string $projectId = null,
): BuildRunner {
    if ($organization === null) {
        $organization = Organization::query()->create([
            'name' => 'Pool Org',
            'slug' => 'pool-org-'.Str::random(6),
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);
        $organization->generateAndStoreMasterKey();
    }

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    return BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => $name,
        'ip_address' => $ipAddress,
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => $maxConcurrentBuilds,
        'supported_runtimes' => $supportedRuntimes,
        'project_id' => $projectId,
        'created_by' => (string) $owner->getKey(),
    ]);
}
