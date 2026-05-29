<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_processes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained('servers');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->string('name');
            $table->text('command');
            $table->string('directory')->nullable();
            $table->string('user')->default('www-data');
            $table->unsignedSmallInteger('processes')->default(1);
            $table->string('status')->default('stopped');
            $table->string('config_path')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('server_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_processes');
    }
};
