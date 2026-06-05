<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex('provisioning_templates_name_unique')) {
            Schema::table('provisioning_templates', function (Blueprint $table): void {
                $table->dropUnique(['name']);
            });
        }

        if (! $this->hasIndex('provisioning_templates_organization_id_name_unique')) {
            Schema::table('provisioning_templates', function (Blueprint $table): void {
                if (Schema::getConnection()->getDriverName() === 'pgsql') {
                    $table->unique(['organization_id', 'name'])->nullsNotDistinct();

                    return;
                }

                $table->unique(['organization_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('provisioning_templates_organization_id_name_unique')) {
            Schema::table('provisioning_templates', function (Blueprint $table): void {
                $table->dropUnique(['organization_id', 'name']);
            });
        }

        if (! $this->hasIndex('provisioning_templates_name_unique')) {
            Schema::table('provisioning_templates', function (Blueprint $table): void {
                $table->unique('name');
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        foreach (Schema::getIndexes('provisioning_templates') as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
