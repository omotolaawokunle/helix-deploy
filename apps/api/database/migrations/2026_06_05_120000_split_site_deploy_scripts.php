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
        Schema::table('sites', function (Blueprint $table): void {
            $table->text('pre_deploy_script')->nullable()->after('deploy_script');
            $table->text('post_deploy_script')->nullable()->after('pre_deploy_script');
        });

        DB::table('sites')
            ->whereNotNull('deploy_script')
            ->where('deploy_script', '!=', '')
            ->update(['post_deploy_script' => DB::raw('deploy_script')]);

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('deploy_script');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->text('deploy_script')->nullable()->after('deploy_branch');
        });

        DB::table('sites')
            ->whereNotNull('post_deploy_script')
            ->where('post_deploy_script', '!=', '')
            ->update(['deploy_script' => DB::raw('post_deploy_script')]);

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['pre_deploy_script', 'post_deploy_script']);
        });
    }
};
