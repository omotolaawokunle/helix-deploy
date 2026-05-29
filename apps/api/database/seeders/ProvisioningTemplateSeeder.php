<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Packages\Provisioning\ProvisioningTemplateLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisioningTemplateSeeder extends Seeder
{
    public function __construct(
        private readonly ProvisioningTemplateLibrary $templateLibrary,
    ) {
    }

    public function run(): void
    {
        $now = now();
        $descriptions = [
            'laravel-stack' => 'Laravel stack with deploy user, Nginx, PHP, MySQL, Redis, and Supervisor.',
            'node-api-stack' => 'Node API stack with deploy user, Nginx, Node.js, and Supervisor.',
            'static-frontend-stack' => 'Static frontend stack with deploy user and Nginx.',
            'worker-stack' => 'Worker stack with deploy user, PHP, Redis, and Supervisor.',
        ];
        $templates = [];

        foreach ($this->templateLibrary->all() as $templateName => $template) {
            $templates[] = [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'name' => $templateName,
                'description' => $descriptions[$templateName] ?? null,
                'services' => json_encode($template['scripts'], JSON_THROW_ON_ERROR),
                'options' => json_encode($template['defaults'], JSON_THROW_ON_ERROR),
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('provisioning_templates')->upsert(
            $templates,
            ['name'],
            ['description', 'services', 'options', 'is_system', 'updated_at', 'organization_id'],
        );
    }
}
