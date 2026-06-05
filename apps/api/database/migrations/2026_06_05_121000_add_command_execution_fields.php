<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table): void {
            $table->string('status', 20)->default('completed')->after('command');
            $table->unsignedSmallInteger('timeout_seconds')->default(60)->after('status');
            $table->timestamp('started_at')->nullable()->after('timeout_seconds');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });

        DB::table('commands')->update([
            'status' => 'completed',
            'started_at' => DB::raw('executed_at'),
            'finished_at' => DB::raw('executed_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table): void {
            $table->dropColumn(['status', 'timeout_seconds', 'started_at', 'finished_at']);
        });
    }
};
