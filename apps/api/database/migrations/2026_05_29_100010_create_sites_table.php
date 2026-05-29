<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained('servers');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('project_id')->nullable()->constrained('projects');
            $table->foreignUuid('environment_id')->nullable()->constrained('environments');
            $table->string('domain');
            $table->jsonb('aliases')->default(new Expression("'[]'::jsonb"));
            $table->string('webroot');
            $table->string('runtime');
            $table->string('deploy_mode')->default('git');
            $table->string('repository_url')->nullable();
            $table->string('repository_provider')->nullable();
            $table->string('deploy_branch')->default('main');
            $table->text('deploy_script')->nullable();
            $table->boolean('run_migrations')->default(true);
            $table->string('docker_image')->nullable();
            $table->string('docker_registry')->nullable();
            $table->string('docker_compose_path')->default('docker-compose.yml');
            $table->string('docker_build_mode')->nullable();
            $table->string('php_version')->nullable();
            $table->string('node_pm')->nullable();
            $table->string('python_wsgi')->nullable();
            $table->string('go_binary_path')->nullable();
            $table->string('go_service_name')->nullable();
            $table->string('status')->default('active');
            $table->uuid('pipeline_id')->nullable();
            $table->timestamps();

            $table->index('server_id');
            $table->index('organization_id');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
