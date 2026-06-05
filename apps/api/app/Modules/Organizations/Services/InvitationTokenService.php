<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Organizations\DTOs\InvitationTokenPayload;
use App\Modules\Organizations\Exceptions\InvalidInvitationTokenException;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\Encryption\Contracts\EncryptionInterface;
use App\Packages\Encryption\EncryptedPayload;
use App\Packages\Encryption\Exceptions\DecryptionFailedException;
use App\Packages\Encryption\MasterKeyManager;
use InvalidArgumentException;
use JsonException;
use ValueError;

class InvitationTokenService
{
    public function __construct(
        private readonly EncryptionInterface $encryption,
        private readonly MasterKeyManager $masterKeyManager,
    ) {
    }

    public function encode(string $organizationId, string $email, TeamRole $role): string
    {
        try {
            $plaintext = json_encode([
                'organization_id' => $organizationId,
                'email' => $email,
                'role' => $role->value,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidInvitationTokenException::malformed();
        }

        $encrypted = $this->encryption->encrypt($plaintext, $this->masterKeyManager->deriveAppKey());

        return $this->toUrlSafeToken($encrypted);
    }

    public function decode(string $token): InvitationTokenPayload
    {
        try {
            $encrypted = $this->fromUrlSafeToken($token);
            $plaintext = $this->encryption->decrypt($encrypted, $this->masterKeyManager->deriveAppKey());

            /** @var array{organization_id?:mixed,email?:mixed,role?:mixed} $data */
            $data = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptionFailedException|InvalidArgumentException|JsonException|ValueError) {
            throw InvalidInvitationTokenException::malformed();
        }

        if (
            ! isset($data['organization_id'], $data['email'], $data['role'])
            || ! is_string($data['organization_id'])
            || ! is_string($data['email'])
            || ! is_string($data['role'])
        ) {
            throw InvalidInvitationTokenException::malformed();
        }

        return new InvitationTokenPayload(
            organizationId: $data['organization_id'],
            email: $data['email'],
            role: TeamRole::from($data['role']),
        );
    }

    private function toUrlSafeToken(EncryptedPayload $payload): string
    {
        return rtrim(strtr(base64_encode($payload->toJson()), '+/', '-_'), '=');
    }

    private function fromUrlSafeToken(string $token): EncryptedPayload
    {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false || $decoded === '') {
            throw InvalidInvitationTokenException::malformed();
        }

        return EncryptedPayload::fromJson($decoded);
    }
}
