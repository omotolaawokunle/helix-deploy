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
        Schema::create('provisioning_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('services');
            $table->jsonb('options')->default(new Expression("'{}'::jsonb"));
            $table->boolean('is_system')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_templates');
    }
};
