<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('build_runners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('ip_address');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user')->default('deploy');
            $table->string('status')->default('online');
            $table->unsignedSmallInteger('max_concurrent_builds')->default(1);
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->jsonb('supported_runtimes')->default('[]');
            $table->uuid('credential_id')->nullable();
            $table->text('fingerprint')->nullable();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
            $table->foreign('credential_id')->references('id')->on('credentials')->nullOnDelete();
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->string('build_strategy')->default('on_server')->after('deploy_branch');
            $table->uuid('build_runner_id')->nullable()->after('build_strategy');
            $table->text('pre_build_script')->nullable()->after('post_deploy_script');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->foreign('build_runner_id')->references('id')->on('build_runners')->nullOnDelete();
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->uuid('build_runner_id')->nullable()->after('pipeline_run_id');
            $table->string('build_strategy')->default('on_server')->after('build_runner_id');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->foreign('build_runner_id')->references('id')->on('build_runners')->nullOnDelete();
        });

        Schema::create('build_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('deployment_id')->constrained('deployments')->cascadeOnDelete();
            $table->foreignUuid('runner_id')->constrained('build_runners')->cascadeOnDelete();
            $table->string('storage_type')->default('local');
            $table->text('storage_path');
            $table->text('checksum');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('runtime');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index('deployment_id');
            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->uuid('build_artifact_id')->nullable()->after('build_strategy');
            $table->foreign('build_artifact_id')->references('id')->on('build_artifacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropForeign(['build_artifact_id']);
            $table->dropColumn('build_artifact_id');
        });

        Schema::dropIfExists('build_artifacts');

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropForeign(['build_runner_id']);
            $table->dropColumn(['build_runner_id', 'build_strategy']);
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropForeign(['build_runner_id']);
            $table->dropColumn(['build_strategy', 'build_runner_id', 'pre_build_script']);
        });

        Schema::dropIfExists('build_runners');
    }
};
