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
            $table->boolean('auto_create_dns')->default(false);
            $table->boolean('is_apex')->default(false);
            $table->foreignUuid('project_dns_zone_id')->nullable()->constrained('project_dns_zones')->nullOnDelete();
            $table->string('dns_zone_id')->nullable();
            $table->string('dns_status')->default('none');
            $table->string('dns_provider')->nullable();
            $table->jsonb('dns_record_ids')->default('[]');
            $table->text('dns_error')->nullable();
            $table->boolean('enable_ssl')->default(false);
            $table->string('ssl_status')->default('none');
            $table->string('ssl_provider')->nullable();
            $table->text('ssl_error')->nullable();
            $table->string('ssl_challenge')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_dns_zone_id');
            $table->dropColumn([
                'auto_create_dns',
                'is_apex',
                'dns_zone_id',
                'dns_status',
                'dns_provider',
                'dns_record_ids',
                'dns_error',
                'enable_ssl',
                'ssl_status',
                'ssl_provider',
                'ssl_error',
                'ssl_challenge',
            ]);
        });
    }
};
