<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Models;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningTemplate extends Model
{
    use HasUuids;

    protected $table = 'provisioning_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'services',
        'options',
        'is_system',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'options' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
