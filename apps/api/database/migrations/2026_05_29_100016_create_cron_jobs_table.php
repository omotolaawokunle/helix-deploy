<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained('servers');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->string('expression');
            $table->text('command');
            $table->string('user')->default('www-data');
            $table->boolean('active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('server_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
