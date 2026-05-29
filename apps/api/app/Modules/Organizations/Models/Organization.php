<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Models;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Models\Team;
use App\Packages\Encryption\MasterKeyManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasUuids;

    protected $table = 'organizations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'master_key_encrypted',
        'settings',
        'owner_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function getMasterKey(): string
    {
        $manager = app(MasterKeyManager::class);
        $payloadJson = (string) $this->master_key_encrypted;

        return $manager->decryptMasterKey(\App\Packages\Encryption\EncryptedPayload::fromJson($payloadJson));
    }

    public function generateAndStoreMasterKey(): void
    {
        $manager = app(MasterKeyManager::class);
        $masterKey = $manager->generateMasterKey();
        $encrypted = $manager->encryptMasterKey($masterKey);
        sodium_memzero($masterKey);

        $this->forceFill([
            'master_key_encrypted' => $encrypted->toJson(),
        ]);

        $this->save();
    }
}
