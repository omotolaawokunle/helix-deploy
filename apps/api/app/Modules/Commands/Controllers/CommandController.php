<?php

declare(strict_types=1);

namespace App\Modules\Commands\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commands\Actions\CancelCommandAction;
use App\Modules\Commands\Jobs\RunCommandJob;
use App\Modules\Commands\Models\Command;
use App\Modules\Commands\Requests\RunCommandRequest;
use App\Modules\Commands\Resources\CommandResource;
use App\Modules\Commands\Services\CommandTableFilterService;
use App\Modules\Commands\Services\CommandService;
use App\Modules\Commands\Services\DangerousCommandGuard;
use App\Modules\Servers\Models\Server;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommandController extends Controller
{
    public function index(
        string $server,
        Request $request,
        CommandTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $serverModel = $this->resolveServer($server);
        $this->authorize('viewAny', [Command::class, $serverModel]);

        $commands = $tableFilterService->paginate(
            query: Command::query()
                ->withoutGlobalScope('owned_by_organization')
                ->where('server_id', (string) $serverModel->getKey())
                ->where('organization_id', (string) $serverModel->organization_id)
                ->with('user'),
            request: $request,
        );

        return CommandResource::collection($commands);
    }

    public function store(
        string $server,
        RunCommandRequest $request,
        DangerousCommandGuard $dangerousCommandGuard,
        CommandService $commandService,
    ): JsonResponse {
        $serverModel = $this->resolveServer($server);
        $this->authorize('create', [Command::class, $serverModel]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $command = $request->command();

        $dangerousCommandGuard->check($command);

        $warningType = $dangerousCommandGuard->warningType($command);

        if ($warningType !== null && ! $request->isConfirmed()) {
            return response()->json([
                'status' => 'confirmation_required',
                'reason' => $warningType->reason(),
                'warningType' => $warningType->value,
            ]);
        }

        $organization = $actor->currentOrganization();
        abort_unless($organization !== null, 422, 'No active organization selected.');

        $record = $commandService->queue(
            $serverModel,
            $command,
            $actor,
            $organization,
            $request->timeoutSeconds(),
        );

        RunCommandJob::dispatch((string) $record->getKey());

        return CommandResource::make($record)
            ->additional([
                'message' => 'Command execution has been queued.',
                'status' => 'queued',
            ])
            ->response()
            ->setStatusCode(202);
    }

    public function cancel(
        string $command,
        Request $request,
        CancelCommandAction $cancelCommandAction,
    ): CommandResource {
        $commandModel = $this->resolveCommand($command);
        $this->authorize('cancel', $commandModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        try {
            $cancelled = $cancelCommandAction->execute($commandModel, $actor);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return CommandResource::make($cancelled->load('user'));
    }

    private function resolveCommand(string $commandId): Command
    {
        $command = Command::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($commandId)
            ->first();

        if ($command === null) {
            throw (new ModelNotFoundException())->setModel(Command::class, [$commandId]);
        }

        return $command;
    }

    private function resolveServer(string $serverId): Server
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->first();

        if ($server === null) {
            throw (new ModelNotFoundException())->setModel(Server::class, [$serverId]);
        }

        return $server;
    }
}
