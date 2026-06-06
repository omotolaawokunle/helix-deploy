<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_dns_zones', function (Blueprint $table): void {
            $table->string('dns_provider')->default('cloudflare')->after('project_id');
        });

        Schema::table('project_dns_zones', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'zone_id']);
            $table->unique(['project_id', 'dns_provider', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::table('project_dns_zones', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'dns_provider', 'zone_id']);
            $table->unique(['project_id', 'zone_id']);
            $table->dropColumn('dns_provider');
        });
    }
};
