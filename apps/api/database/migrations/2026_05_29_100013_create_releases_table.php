<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites');
            $table->foreignUuid('deployment_id')->constrained('deployments');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->string('path');
            $table->string('commit_hash');
            $table->boolean('is_active')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
