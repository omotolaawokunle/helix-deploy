<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('project_id')->nullable()->constrained('projects');
            $table->foreignUuid('environment_id')->nullable()->constrained('environments');
            $table->string('hostname');
            $table->string('ip_address');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user')->default('deploy');
            $table->string('provider')->default('generic');
            $table->string('region')->nullable();
            $table->string('server_type')->nullable();
            $table->string('os')->nullable();
            $table->string('php_version')->nullable();
            $table->string('node_version')->nullable();
            $table->string('status')->default('connecting');
            $table->string('management_mode')->default('managed');
            $table->text('fingerprint')->nullable();
            $table->uuid('credential_id')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->jsonb('installed_services')->default('{}');
            $table->jsonb('health_status')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
            $table->index(['project_id', 'environment_id']);
        });

        Schema::table('servers', function (Blueprint $table): void {
            $table->foreign('credential_id')->references('id')->on('credentials');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('current_organization_id')->references('id')->on('organizations');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['current_organization_id']);
        });

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropForeign(['credential_id']);
        });

        Schema::dropIfExists('servers');
    }
};
