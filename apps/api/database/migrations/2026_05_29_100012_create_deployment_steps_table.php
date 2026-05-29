<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('deployment_id')->constrained('deployments')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->text('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->unsignedSmallInteger('order');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['deployment_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_steps');
    }
};
