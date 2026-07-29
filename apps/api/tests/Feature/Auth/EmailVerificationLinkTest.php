<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Auth\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifies email via signed verification link', function (): void {
    config([
        'helixdeploy.spa_url' => 'http://localhost:5173',
        'helixdeploy.verification_url_root' => 'http://localhost',
    ]);

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

it('rejects verification link when signed and requested origins differ', function (): void {
    config([
        'helixdeploy.verification_url_root' => 'http://localhost:8000',
    ]);

    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->email),
    ]);

    expect($url)->toContain('http://localhost:8000/email/verify/');

    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $this->get("http://localhost{$path}?{$query}", [
        'HTTP_X_FORWARDED_PROTO' => 'http',
        'HTTP_X_FORWARDED_HOST' => 'localhost',
        'HTTP_X_FORWARDED_PORT' => '80',
    ])->assertForbidden();
});

it('builds verification links using the configured public origin', function (): void {
    config([
        'helixdeploy.verification_url_root' => 'http://localhost',
    ]);

    Notification::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, QueuedVerifyEmail::class, function (QueuedVerifyEmail $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return is_string($url)
            && str_starts_with($url, 'http://localhost/email/verify/')
            && str_contains($url, 'signature=');
    });
});
