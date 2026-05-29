<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('order');
            $table->jsonb('config')->default('{}');
            $table->boolean('requires_approval')->default(false);
            $table->string('approver_role')->nullable();
            $table->unsignedSmallInteger('retry_attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_steps');
    }
};
