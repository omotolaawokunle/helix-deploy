<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_challenge');
            $table->timestamp('ssl_checked_at')->nullable()->after('ssl_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['ssl_expires_at', 'ssl_checked_at']);
        });
    }
};
