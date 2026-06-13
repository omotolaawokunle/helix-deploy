<?php

declare(strict_types=1);

use App\Packages\SSH\FakeSSHConnection;

function queueSslExpiryInspectionResponses(
    FakeSSHConnection $fake,
    string $domain,
    bool $certbotIncludesDomain = true,
): void {
    $certbotOutput = $certbotIncludesDomain
        ? sprintf(
            "Certificate Name: %s\n    Domains: %s\n    Expiry Date: 2026-06-30 12:00:00+00:00 (VALID: 30 days)\n",
            $domain,
            $domain,
        )
        : "Certificate Name: other.example.test\n    Domains: other.example.test\n    Expiry Date: 2026-01-01 00:00:00+00:00 (VALID: 1 days)\n";

    $fake->addSequence('*certbot certificates*', sshFailure(), sshSuccess($certbotOutput));
    $fake->addSequence('*openssl x509*', sshFailure(), sshSuccess('notAfter=Jun 30 12:00:00 2026 GMT'));
}
