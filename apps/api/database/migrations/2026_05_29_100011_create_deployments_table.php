<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->string('type')->default('deploy');
            $table->string('status')->default('pending');
            $table->foreignUuid('triggered_by')->nullable()->constrained('users');
            $table->string('trigger_type')->default('manual');
            $table->string('branch')->nullable();
            $table->string('commit_hash')->nullable();
            $table->text('commit_message')->nullable();
            $table->string('release_path')->nullable();
            $table->uuid('rollback_target_id')->nullable();
            $table->text('rollback_reason')->nullable();
            $table->uuid('pipeline_run_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('status');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->foreign('rollback_target_id')->references('id')->on('deployments');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropForeign(['rollback_target_id']);
        });

        Schema::dropIfExists('deployments');
    }
};
