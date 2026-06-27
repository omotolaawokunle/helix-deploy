<?php

declare(strict_types=1);

use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $serverMorph = (new Server)->getMorphClass();

        DB::table('credentials')
            ->where('credentialable_type', $serverMorph)
            ->where('type', CredentialType::ENV_VAR->value)
            ->where(function ($query): void {
                $query->where('name', 'like', '%-postgresql-deploy-password')
                    ->orWhere('name', 'like', '%-mysql-deploy-password')
                    ->orWhere('name', 'like', '%-redis-password');
            })
            ->update(['type' => CredentialType::SERVER_SECRET->value]);
    }

    public function down(): void
    {
        $serverMorph = (new Server)->getMorphClass();

        DB::table('credentials')
            ->where('credentialable_type', $serverMorph)
            ->where('type', CredentialType::SERVER_SECRET->value)
            ->update(['type' => CredentialType::ENV_VAR->value]);
    }
};
