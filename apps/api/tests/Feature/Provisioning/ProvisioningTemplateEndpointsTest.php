<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use App\Modules\Teams\Enums\TeamRole;
use Database\Seeders\ProvisioningTemplateSeeder;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createProvisioningTemplateFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Provisioning Templates Org',
        'slug' => 'provisioning-templates-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    return [$organization, $user];
}

it('lists system and org provisioning templates with crud and audit logs', function (): void {
    [$organization, $owner] = createProvisioningTemplateFixture();

    $this->seed(ProvisioningTemplateSeeder::class);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/provisioning-templates", [
            'per_page' => 100,
        ])
        ->assertOk()
        ->assertJsonFragment(['name' => 'laravel-stack', 'isSystem' => true]);

    $createResponse = $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/provisioning-templates", [
            'name' => 'custom-stack',
            'description' => 'Custom org template',
            'services' => ['create-deploy-user', 'nginx', 'php'],
            'options' => ['phpVersion' => '8.3'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'custom-stack')
        ->assertJsonPath('data.isSystem', false);

    $templateId = (string) $createResponse->json('data.id');

    expect(AuditLog::query()->where('operation', 'provisioning_template.created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->patchJson("/api/v1/provisioning-templates/{$templateId}", [
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated description');

    $this->actingAs($owner)
        ->deleteJson("/api/v1/provisioning-templates/{$templateId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'provisioning_template.deleted')->exists())->toBeTrue();
});

it('forbids deleting system provisioning templates', function (): void {
    [$organization, $owner] = createProvisioningTemplateFixture();

    $this->seed(ProvisioningTemplateSeeder::class);

    $systemTemplate = ProvisioningTemplate::query()
        ->where('is_system', true)
        ->where('name', 'laravel-stack')
        ->firstOrFail();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/provisioning-templates/{$systemTemplate->id}")
        ->assertForbidden();
});

it('forbids cross organization provisioning template access', function (): void {
    [$firstOrg, $firstOwner] = createProvisioningTemplateFixture();
    [$secondOrg] = createProvisioningTemplateFixture();

    $template = ProvisioningTemplate::query()->create([
        'organization_id' => (string) $secondOrg->getKey(),
        'name' => 'private-template',
        'description' => null,
        'services' => ['nginx'],
        'options' => [],
        'is_system' => false,
        'created_by' => null,
    ]);

    $this->actingAs($firstOwner)
        ->getJson("/api/v1/provisioning-templates/{$template->id}")
        ->assertForbidden();
});

it('forbids developers from creating provisioning templates', function (): void {
    [$organization, $developer] = createProvisioningTemplateFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/provisioning-templates", [
            'name' => 'dev-template',
            'services' => ['nginx'],
        ])
        ->assertForbidden();
});
