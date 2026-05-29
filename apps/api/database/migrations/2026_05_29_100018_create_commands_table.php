<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained('servers');
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('user_id')->constrained('users');
            $table->text('command');
            $table->text('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('executed_at')->useCurrent();

            $table->index(['server_id', 'executed_at']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
