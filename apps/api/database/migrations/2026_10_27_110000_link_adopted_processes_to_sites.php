<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_processes', function (Blueprint $table): void {
            $table->foreignUuid('site_id')->nullable()->after('server_id')->constrained('sites')->nullOnDelete();
            $table->index('site_id');
        });

        Schema::table('cron_jobs', function (Blueprint $table): void {
            $table->foreignUuid('site_id')->nullable()->after('server_id')->constrained('sites')->nullOnDelete();
            $table->boolean('external')->default(false)->after('active');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('cron_jobs', function (Blueprint $table): void {
            $table->dropIndex(['site_id']);
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn('external');
        });

        Schema::table('supervisor_processes', function (Blueprint $table): void {
            $table->dropIndex(['site_id']);
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
