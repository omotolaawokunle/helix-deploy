<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Provisioning\DTOs\ProvisionServerDTO;
use App\Modules\Provisioning\Jobs\ProvisionServerJob;
use App\Modules\Servers\Models\Server;
use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProvisioningController extends Controller
{
    public function provision(Server $server, Request $request): JsonResponse
    {
        $this->authorize('provision', $server);

        $validated = $request->validate([
            'scripts' => ['required', 'array', 'min:1'],
            'scripts.*' => [
                'required',
                'string',
                Rule::in([
                    'create-deploy-user',
                    'nginx',
                    'php',
                    'mysql',
                    'postgresql',
                    'redis',
                    'nodejs',
                    'python',
                    'supervisor',
                    'docker',
                    'certbot',
                ]),
            ],
            'options' => ['nullable', 'array'],
            'options.phpVersion' => ['nullable', 'string', Rule::in(PhpVersion::values())],
            'options.nodeVersion' => ['nullable', 'integer', Rule::in(NodejsVersion::values())],
            'options.redisPassword' => ['nullable', 'string', 'min:8'],
        ]);

        $request->replace($validated);
        $dto = ProvisionServerDTO::fromRequest($request);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $runId = (string) Str::uuid();

        ProvisionServerJob::dispatch(
            serverId: (string) $server->getKey(),
            actorId: (string) $actor->getKey(),
            runId: $runId,
            scripts: $dto->scripts,
            options: $dto->options,
        );

        return response()->json([
            'jobId' => $runId,
            'channel' => "server.{$server->getKey()}.provisioning",
        ], 202);
    }
}
