<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisioningTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $templates = [
            [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'name' => 'Laravel Stack',
                'description' => 'PHP-FPM, Nginx, Redis, PostgreSQL, and queue worker baseline.',
                'services' => json_encode([
                    'nginx',
                    'php-fpm',
                    'redis',
                    'postgresql',
                    'queue-worker',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode([
                    'php_version' => '8.3',
                    'node_version' => '22',
                    'queue' => true,
                ], JSON_THROW_ON_ERROR),
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'name' => 'Node API Stack',
                'description' => 'Node.js API runtime with reverse proxy and process supervisor.',
                'services' => json_encode([
                    'nginx',
                    'node',
                    'pm2',
                    'redis',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode([
                    'node_version' => '22',
                    'package_manager' => 'pnpm',
                    'process_manager' => 'pm2',
                ], JSON_THROW_ON_ERROR),
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'name' => 'Static Frontend Stack',
                'description' => 'Nginx-only static hosting with edge cache and gzip defaults.',
                'services' => json_encode([
                    'nginx',
                    'certbot',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode([
                    'cache_static_assets' => true,
                    'gzip_enabled' => true,
                ], JSON_THROW_ON_ERROR),
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'name' => 'Worker Stack',
                'description' => 'Background worker host with supervisor and optional scheduler.',
                'services' => json_encode([
                    'supervisor',
                    'queue-worker',
                ], JSON_THROW_ON_ERROR),
                'options' => json_encode([
                    'queue_concurrency' => 4,
                    'run_scheduler' => true,
                ], JSON_THROW_ON_ERROR),
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('provisioning_templates')->upsert(
            $templates,
            ['name'],
            ['description', 'services', 'options', 'is_system', 'updated_at'],
        );
    }
}
