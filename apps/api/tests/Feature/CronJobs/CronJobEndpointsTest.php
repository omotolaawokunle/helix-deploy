<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CronJobs\Jobs\SyncCronJobsJob;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns 422 for invalid cron expressions', function (): void {
    [$server, $owner] = cronJobApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/cron-jobs", [
            'expression' => 'not a cron',
            'command' => 'php artisan schedule:run',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expression']);
});

it('queues cron sync after creating a job', function (): void {
    Queue::fake();

    [$server, $owner] = cronJobApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/cron-jobs", [
            'expression' => '0 0 * * *',
            'command' => 'php artisan inspire',
        ])
        ->assertCreated()
        ->assertJsonPath('data.description', 'Every day at midnight');

    Queue::assertPushed(SyncCronJobsJob::class);
});

/**
 * @return array{0: Server, 1: User}
 */
function cronJobApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Cron API Org',
        'slug' => 'cron-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'cron-api.test',
        'ip_address' => '10.0.0.21',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return [$server, $owner];
}
