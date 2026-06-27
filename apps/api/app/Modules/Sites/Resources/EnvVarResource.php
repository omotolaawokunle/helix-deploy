<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\Credentials\Models\Credential;
use App\Modules\Servers\Services\ServerServiceCredentialRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Credential
 */
class EnvVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isReference = $this->referenced_credential_id !== null;
        $referencedLabel = null;

        if ($isReference && $this->relationLoaded('referencedCredential') && $this->referencedCredential !== null) {
            $metadata = app(ServerServiceCredentialRegistry::class)
                ->metadataForName((string) $this->referencedCredential->name);
            $referencedLabel = $metadata['label'] ?? null;
        }

        return [
            'id' => (string) $this->getKey(),
            'key' => $this->name,
            'maskedValue' => '••••••••',
            'isReference' => $isReference,
            'referencedCredentialId' => $this->referenced_credential_id,
            'referencedCredentialLabel' => $referencedLabel,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
