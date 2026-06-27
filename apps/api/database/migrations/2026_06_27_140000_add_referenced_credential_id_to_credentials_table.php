<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table): void {
            $table->foreignUuid('referenced_credential_id')
                ->nullable()
                ->after('credentialable_id')
                ->constrained('credentials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table): void {
            $table->dropForeign(['referenced_credential_id']);
            $table->dropColumn('referenced_credential_id');
        });
    }
};
