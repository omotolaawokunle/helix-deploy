<?php

declare(strict_types=1);

use App\Modules\Organizations\Exceptions\InvalidInvitationTokenException;
use App\Modules\Organizations\Services\InvitationTokenService;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\Encryption\MasterKeyManager;
use App\Packages\Encryption\SodiumEncryption;
use Illuminate\Support\Str;

it('encodes and decodes invitation payload without exposing plaintext parameters', function (): void {
    $service = new InvitationTokenService(
        encryption: new SodiumEncryption(new \App\Packages\Encryption\KeyGenerator()),
        masterKeyManager: new MasterKeyManager(
            encryption: new SodiumEncryption(new \App\Packages\Encryption\KeyGenerator()),
            appKey: 'base64:'.base64_encode(random_bytes(32)),
        ),
    );

    $organizationId = (string) Str::uuid();
    $email = 'invitee@example.test';

    $token = $service->encode(
        organizationId: $organizationId,
        email: $email,
        role: TeamRole::DEVELOPER,
    );

    expect($token)->not->toContain($organizationId)
        ->and($token)->not->toContain($email)
        ->and($token)->not->toContain(TeamRole::DEVELOPER->value);

    $payload = $service->decode($token);

    expect($payload->organizationId)->toBe($organizationId)
        ->and($payload->email)->toBe($email)
        ->and($payload->role)->toBe(TeamRole::DEVELOPER);
});

it('rejects tampered invitation tokens', function (): void {
    $service = new InvitationTokenService(
        encryption: new SodiumEncryption(new \App\Packages\Encryption\KeyGenerator()),
        masterKeyManager: new MasterKeyManager(
            encryption: new SodiumEncryption(new \App\Packages\Encryption\KeyGenerator()),
            appKey: 'base64:'.base64_encode(random_bytes(32)),
        ),
    );

    $token = $service->encode(
        organizationId: (string) Str::uuid(),
        email: 'invitee@example.test',
        role: TeamRole::VIEWER,
    );

    $tampered = substr($token, 0, -4).'aaaa';

    expect(fn () => $service->decode($tampered))
        ->toThrow(InvalidInvitationTokenException::class, 'Invalid invitation link.');
});
