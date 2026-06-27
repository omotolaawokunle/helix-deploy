<?php

declare(strict_types=1);

use App\Modules\Sites\Services\SiteSslCertificateInspector;
use App\Packages\SSH\FakeSSHConnection;
use Illuminate\Support\Carbon;

it('parses openssl enddate output', function (): void {
    $inspector = new SiteSslCertificateInspector();

    $parsed = $inspector->parseOpenSslEndDate('notAfter=Dec 31 23:59:59 2026 GMT');

    expect($parsed)->toBeInstanceOf(Carbon::class)
        ->and($parsed?->year)->toBe(2026);
});

it('returns null for empty openssl output', function (): void {
    $inspector = new SiteSslCertificateInspector();

    expect($inspector->parseOpenSslEndDate(''))->toBeNull();
});

it('parses certbot certificates output for a matching domain', function (): void {
    $inspector = new SiteSslCertificateInspector();

    $output = <<<'OUTPUT'
Found the following certs:
  Certificate Name: getlectern.com
    Domains: getlectern.com www.getlectern.com
    Expiry Date: 2026-05-15 12:00:00+00:00 (VALID: 45 days)
    Certificate Path: /etc/letsencrypt/live/getlectern.com/fullchain.pem
OUTPUT;

    $parsed = $inspector->parseCertbotCertificatesOutput($output, 'getlectern.com');

    expect($parsed)->toBeInstanceOf(Carbon::class)
        ->and($parsed?->year)->toBe(2026)
        ->and($parsed?->month)->toBe(5);
});

it('reads certificate expiry over ssh for active sites via certbot', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $site->forceFill([
        'ssl_status' => \App\Modules\Sites\Enums\SslStatus::ACTIVE->value,
    ])->save();

    queueSslExpiryInspectionResponses($fake, domain: $site->domain);

    $inspector = new SiteSslCertificateInspector();
    $expiresAt = $inspector->inspect($site->refresh(), $fake);

    expect($expiresAt)->toBeInstanceOf(Carbon::class)
        ->and($expiresAt?->year)->toBe(2026);
});

it('finds certificate expiry by domain without requiring helix ssl status', function (): void {
    $fake = (new FakeSSHConnection())->connect();
    queueSslExpiryInspectionResponses($fake, domain: 'secure.example.test');

    $inspector = new SiteSslCertificateInspector();
    $expiresAt = $inspector->findCertificateExpiry('secure.example.test', $fake);

    expect($expiresAt)->toBeInstanceOf(Carbon::class)
        ->and($expiresAt?->year)->toBe(2026);
});

it('falls back to openssl when certbot output does not include the domain', function (): void {
    $fake = (new FakeSSHConnection())->connect();
    queueSslExpiryInspectionResponses($fake, domain: 'missing.example.test', certbotIncludesDomain: false);

    $inspector = new SiteSslCertificateInspector();

    expect($inspector->findCertificateExpiry('missing.example.test', $fake))->toBeInstanceOf(Carbon::class);
});

it('returns null when no certificate can be read', function (): void {
    $fake = (new FakeSSHConnection())->connect();
    $fake->addSequence('*certbot certificates*', sshFailure(), sshFailure());
    $fake->addSequence('*openssl x509*', sshFailure(), sshFailure());

    $inspector = new SiteSslCertificateInspector();

    expect($inspector->findCertificateExpiry('missing.example.test', $fake))->toBeNull();
});

it('returns null when openssl command yields no date', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $site->forceFill([
        'ssl_status' => \App\Modules\Sites\Enums\SslStatus::ACTIVE->value,
    ])->save();

    $fake->addSequence('*certbot certificates*', sshFailure(), sshFailure());
    $fake->addSequence('*openssl x509*', sshFailure(), sshSuccess(''));

    $inspector = new SiteSslCertificateInspector();

    expect($inspector->inspect($site->refresh(), $fake))->toBeNull();
});
