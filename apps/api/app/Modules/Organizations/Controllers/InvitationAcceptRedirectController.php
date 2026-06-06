<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationAcceptRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $spaUrl = rtrim((string) config('helixdeploy.spa_url'), '/');
        $query = $request->getQueryString();

        $destination = $query !== null && $query !== ''
            ? "{$spaUrl}/accept-invitation?{$query}"
            : "{$spaUrl}/accept-invitation";

        return redirect()->away($destination);
    }
}
