<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Shared\Concerns\OwnedByOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasUuids;
    use OwnedByOrganization;

    protected $table = 'audit_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'actor_id',
        'operation',
        'resource_type',
        'resource_id',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
        'request_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('AuditLog records are immutable');
        }

        return parent::save($options);
    }

    public function delete(): bool|null
    {
        throw new \LogicException('AuditLog records cannot be deleted');
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     */
    public static function record(
        string $operation,
        ?Model $resource = null,
        array $metadata = [],
        ?array $beforeState = null,
        ?array $afterState = null,
    ): self {
        $request = request();
        $authUser = Auth::user();
        $organizationId = method_exists($authUser, 'currentOrganization')
            ? $authUser?->currentOrganization()?->getKey()
            : null;

        if ($organizationId === null) {
            $resourceOrganizationId = $resource?->getAttribute('organization_id');
            $organizationId = is_string($resourceOrganizationId) ? $resourceOrganizationId : null;
        }

        $normalizedAfterState = $afterState ?? [];

        if ($metadata !== []) {
            $normalizedAfterState['_metadata'] = $metadata;
        }

        $model = new self([
            'organization_id' => $organizationId,
            'actor_id' => Auth::id(),
            'operation' => $operation,
            'resource_type' => $resource?->getMorphClass(),
            'resource_id' => $resource?->getKey(),
            'before_state' => $beforeState,
            'after_state' => $normalizedAfterState === [] ? null : $normalizedAfterState,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->header('X-Request-ID') ?? (string) Str::uuid(),
            'created_at' => now(),
        ]);

        $model->save();

        return $model;
    }
}
