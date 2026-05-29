<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_group_server', function (Blueprint $table): void {
            $table->foreignUuid('server_group_id')->constrained('server_groups')->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained('servers')->cascadeOnDelete();

            $table->primary(['server_group_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_group_server');
    }
};
