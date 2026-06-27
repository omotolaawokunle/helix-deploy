<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Http\Controllers\Controller;
use App\Packages\Provisioning\ProvisioningVersionCatalog;
use Illuminate\Http\JsonResponse;

class ProvisioningVersionController extends Controller
{
    public function index(ProvisioningVersionCatalog $catalog): JsonResponse
    {
        return response()->json([
            'data' => $catalog->toArray(),
        ]);
    }
}
