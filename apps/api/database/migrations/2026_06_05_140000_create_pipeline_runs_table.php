<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('pipeline_id')->constrained('pipelines');
            $table->foreignUuid('site_id')->constrained('sites');
            $table->foreignUuid('deployment_id')->nullable()->constrained('deployments');
            $table->foreignUuid('triggered_by')->constrained('users');
            $table->string('status');
            $table->unsignedInteger('current_step_order')->default(0);
            $table->jsonb('metadata')->default('[]');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('site_id');
        });

        Schema::create('pipeline_run_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pipeline_run_id')->constrained('pipeline_runs')->cascadeOnDelete();
            $table->foreignUuid('pipeline_step_id')->nullable()->constrained('pipeline_steps')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedInteger('order');
            $table->string('status');
            $table->jsonb('config')->default('[]');
            $table->boolean('requires_approval')->default(false);
            $table->string('approver_role')->nullable();
            $table->unsignedTinyInteger('retry_attempts')->default(0);
            $table->unsignedTinyInteger('attempts_made')->default(0);
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['pipeline_run_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_run_steps');
        Schema::dropIfExists('pipeline_runs');
    }
};
