<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('verifies email via signed verification link', function (): void {
    config(['helixdeploy.spa_url' => 'http://localhost:5173']);

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->email),
    ]);

    $this->get($url)
        ->assertRedirect('http://localhost:5173/verify-email?verified=1');

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

it('rejects verification link with invalid signature', function (): void {
    $user = User::factory()->unverified()->create();

    $this->get("/email/verify/{$user->getKey()}/".sha1((string) $user->email))
        ->assertForbidden();
});
