<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendInvitationEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $invitationUrl,
    ) {
    }

    public function handle(): void
    {
        Log::info('Organization invitation URL generated.', [
            'email' => $this->email,
            'invitation_url' => $this->invitationUrl,
        ]);
    }
}
