<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->uuid('run_id');
            $table->text('line');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['server_id', 'run_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_logs');
    }
};
