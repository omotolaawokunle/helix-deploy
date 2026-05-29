<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('credentialable_type')->nullable();
            $table->uuid('credentialable_id')->nullable();
            $table->string('type');
            $table->string('name');
            $table->text('encrypted_value');
            $table->text('nonce');
            $table->string('key_fingerprint')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['credentialable_type', 'credentialable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
