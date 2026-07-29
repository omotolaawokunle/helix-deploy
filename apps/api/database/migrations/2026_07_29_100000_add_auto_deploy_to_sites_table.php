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
            $table->boolean('auto_deploy_enabled')->default(false)->after('deploy_branch');
            $table->string('webhook_token', 64)->nullable()->unique()->after('auto_deploy_enabled');
            $table->text('webhook_secret_encrypted')->nullable()->after('webhook_token');
            $table->string('webhook_secret_nonce', 64)->nullable()->after('webhook_secret_encrypted');
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->dropForeign(['triggered_by']);
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->uuid('triggered_by')->nullable()->change();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->dropForeign(['triggered_by']);
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->uuid('triggered_by')->nullable(false)->change();
            $table->foreign('triggered_by')->references('id')->on('users');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_deploy_enabled',
                'webhook_token',
                'webhook_secret_encrypted',
                'webhook_secret_nonce',
            ]);
        });
    }
};
