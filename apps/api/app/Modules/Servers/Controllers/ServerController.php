<?php

declare(strict_types=1);

namespace App\Modules\Servers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Actions\DeleteServerAction;
use App\Modules\Servers\Actions\ImportServerAction;
use App\Modules\Servers\Actions\RegisterServerAction;
use App\Modules\Servers\Actions\TestConnectionAction;
use App\Modules\Servers\DTOs\RegisterServerDTO;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Requests\RegisterServerRequest;
use App\Modules\Servers\Requests\UpdateServerRequest;
use App\Modules\Servers\Resources\ServerRegistrationResource;
use App\Modules\Servers\Resources\ServerResource;
use App\Modules\Servers\Services\ServerTableFilterService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Teams\Contracts\TeamProjectVisibilityServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        ServerTableFilterService $tableFilterService,
        TeamProjectVisibilityServiceInterface $visibilityService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [Server::class, $org]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $query = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->where('organization_id', (string) $org->getKey())
            ->select('servers.*')
            ->with(['project', 'environment'])
            ->withCount([
                'sites as active_ssl_count' => static fn ($siteQuery) => $siteQuery
                    ->where('enable_ssl', true)
                    ->where('ssl_status', SslStatus::ACTIVE->value),
                'sites as expiring_ssl_count' => static fn ($siteQuery) => $siteQuery
                    ->where('enable_ssl', true)
                    ->where('ssl_status', SslStatus::ACTIVE->value)
                    ->whereNotNull('ssl_expires_at')
                    ->where('ssl_expires_at', '<=', now()->addDays(30)),
            ])
            ->withMin([
                'sites as nearest_ssl_expiry' => static fn ($siteQuery) => $siteQuery
                    ->where('enable_ssl', true)
                    ->where('ssl_status', SslStatus::ACTIVE->value)
                    ->whereNotNull('ssl_expires_at'),
            ], 'ssl_expires_at');

        $visibleProjectIds = $visibilityService->visibleProjectIds($user, $org);

        if ($visibleProjectIds !== null) {
            $query->whereIn('project_id', $visibleProjectIds);
        }

        $servers = $tableFilterService->paginate(
            query: $query,
            request: $request,
        );

        return ServerResource::collection($servers);
    }

    public function store(
        Organization $org,
        RegisterServerRequest $request,
        RegisterServerAction $registerServerAction,
        ImportServerAction $importServerAction,
    ): ServerRegistrationResource {
        $this->authorize('create', [Server::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $dto = RegisterServerDTO::fromRequest($request);

        $result = $dto->authMethod === 'import'
            ? $importServerAction->execute($org, $actor, $dto)
            : $registerServerAction->execute($org, $actor, $dto);

        return ServerRegistrationResource::make($result);
    }

    public function show(string $server): ServerResource
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('view', $serverModel);

        return ServerResource::make($serverModel->loadMissing(['project', 'environment']));
    }

    public function update(string $server, UpdateServerRequest $request): ServerResource
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('update', $serverModel);

        $validated = $request->validated();
        $updates = [];
        $beforeState = [];
        $afterState = [];

        if (array_key_exists('name', $validated)) {
            $updates['hostname'] = (string) $validated['name'];
        }

        if (array_key_exists('tags', $validated)) {
            $updates['tags'] = $validated['tags'];
        }

        if (array_key_exists('projectId', $validated)) {
            $project = $this->resolveProject($validated['projectId'], (string) $serverModel->organization_id);
            $updates['project_id'] = $project?->getKey();

            if ((string) $serverModel->project_id !== (string) ($project?->getKey() ?? '')) {
                $beforeState['project_id'] = $serverModel->project_id;
                $afterState['project_id'] = $project?->getKey();
            }

            if (! array_key_exists('environmentId', $validated)) {
                $updates['environment_id'] = null;
            }
        }

        $projectIdForEnvironment = array_key_exists('project_id', $updates)
            ? $updates['project_id']
            : ($serverModel->project_id !== null ? (string) $serverModel->project_id : null);

        if (array_key_exists('environmentId', $validated)) {
            $environment = $this->resolveEnvironment(
                $validated['environmentId'],
                (string) $serverModel->organization_id,
                $projectIdForEnvironment,
            );
            $updates['environment_id'] = $environment?->getKey();
        }

        if ($updates !== []) {
            $serverModel->forceFill($updates)->save();
        }

        if ($beforeState !== [] || $afterState !== []) {
            AuditLog::record(
                operation: 'server.project_assignment_changed',
                resource: $serverModel,
                beforeState: $beforeState,
                afterState: $afterState,
            );
        }

        return ServerResource::make($serverModel->refresh()->loadMissing(['project', 'environment']));
    }

    public function destroy(string $server, Request $request, DeleteServerAction $action): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('delete', $serverModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute($serverModel, $actor);

        return response()->json(status: 202);
    }

    public function testConnection(string $server, Request $request, TestConnectionAction $action): JsonResponse
    {
        $serverModel = $this->resolveServer($server);
        $this->authorize('update', $serverModel);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $action->execute($serverModel, $actor);

        return response()->json(status: 202);
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

    private function resolveProject(?string $projectId, string $organizationId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        $project = Project::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($projectId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($project === null) {
            throw (new ModelNotFoundException())->setModel(Project::class, [$projectId]);
        }

        return $project;
    }

    private function resolveEnvironment(?string $environmentId, string $organizationId, string|null $projectId): ?Environment
    {
        if ($environmentId === null) {
            return null;
        }

        $query = Environment::query()
            ->whereKey($environmentId)
            ->where('organization_id', $organizationId);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $environment = $query->first();

        if ($environment === null) {
            throw (new ModelNotFoundException())->setModel(Environment::class, [$environmentId]);
        }

        return $environment;
    }
}
