<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Models;

use App\Models\User;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Credential extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'credentialable_type',
        'credentialable_id',
        'referenced_credential_id',
        'type',
        'name',
        'encrypted_value',
        'nonce',
        'key_fingerprint',
        'created_by',
        'last_used_at',
    ];

    protected $hidden = [
        'encrypted_value',
        'nonce',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CredentialType::class,
            'last_used_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credentialable(): MorphTo
    {
        return $this->morphTo();
    }

    public function referencedCredential(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referenced_credential_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(\Illuminate\Database\Eloquent\Builder $query, Organization $organization): \Illuminate\Database\Eloquent\Builder
    {
        return $query->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', $organization->getKey());
    }

    public function scopeOfType(\Illuminate\Database\Eloquent\Builder $query, CredentialType $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        unset($data['encrypted_value'], $data['nonce']);

        return $data;
    }
}
