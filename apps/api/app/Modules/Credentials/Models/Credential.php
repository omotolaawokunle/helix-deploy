<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Models;

use App\Models\Organization;
use App\Modules\Credentials\Enums\CredentialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Credential extends Model
{
    use HasUuids;

    protected $table = 'credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'credentialable_type',
        'credentialable_id',
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

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->getKey());
    }

    public function scopeOfType(Builder $query, CredentialType $type): Builder
    {
        return $query->where('type', $type->value);
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
