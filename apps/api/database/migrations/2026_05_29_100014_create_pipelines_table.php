<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('project_id')->nullable()->constrained('projects');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('stages')->default('[]');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
