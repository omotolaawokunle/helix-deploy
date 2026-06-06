<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Models\User;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Integrations\Enums\CloudflareConnectionStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudflareConnection extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'cloudflare_connections';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'credential_id',
        'status',
        'connected_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CloudflareConnectionStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
