<?php

declare(strict_types=1);

namespace App\Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        $spaUrl = rtrim((string) config('helixdeploy.spa_url'), '/');
        $email = $notifiable->getEmailForPasswordReset();

        return $spaUrl.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }
}
