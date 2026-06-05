<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Provisioning\Actions\CreateProvisioningTemplateAction;
use App\Modules\Provisioning\Actions\DeleteProvisioningTemplateAction;
use App\Modules\Provisioning\Actions\UpdateProvisioningTemplateAction;
use App\Modules\Provisioning\DTOs\CreateProvisioningTemplateDTO;
use App\Modules\Provisioning\DTOs\UpdateProvisioningTemplateDTO;
use App\Modules\Provisioning\Models\ProvisioningTemplate;
use App\Modules\Provisioning\Requests\StoreProvisioningTemplateRequest;
use App\Modules\Provisioning\Requests\UpdateProvisioningTemplateRequest;
use App\Modules\Provisioning\Resources\ProvisioningTemplateResource;
use App\Modules\Provisioning\Services\ProvisioningTemplateTableFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvisioningTemplateController extends Controller
{
    public function index(
        Organization $org,
        Request $request,
        ProvisioningTemplateTableFilterService $tableFilterService,
    ): \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
        $this->authorize('viewAny', [ProvisioningTemplate::class, $org]);

        $templates = $tableFilterService->paginate(
            query: ProvisioningTemplate::query()
                ->where(function (Builder $builder) use ($org): void {
                    $builder
                        ->whereNull('organization_id')
                        ->orWhere('organization_id', (string) $org->getKey());
                }),
            request: $request,
        );

        return ProvisioningTemplateResource::collection($templates);
    }

    public function store(
        Organization $org,
        StoreProvisioningTemplateRequest $request,
        CreateProvisioningTemplateAction $action,
    ): JsonResponse {
        $this->authorize('create', [ProvisioningTemplate::class, $org]);

        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $template = $action->execute(
            $org,
            $actor,
            CreateProvisioningTemplateDTO::fromRequest($request),
        );

        return ProvisioningTemplateResource::make($template)
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $provisioningTemplate): ProvisioningTemplateResource
    {
        $template = $this->resolveTemplate($provisioningTemplate);
        $this->authorize('view', $template);

        return ProvisioningTemplateResource::make($template);
    }

    public function update(
        string $provisioningTemplate,
        UpdateProvisioningTemplateRequest $request,
        UpdateProvisioningTemplateAction $action,
    ): ProvisioningTemplateResource {
        $template = $this->resolveTemplate($provisioningTemplate);
        $this->authorize('update', $template);

        $updated = $action->execute($template, UpdateProvisioningTemplateDTO::fromRequest($request));

        return ProvisioningTemplateResource::make($updated);
    }

    public function destroy(
        string $provisioningTemplate,
        DeleteProvisioningTemplateAction $action,
    ): JsonResponse {
        $template = $this->resolveTemplate($provisioningTemplate);
        $this->authorize('delete', $template);

        $action->execute($template);

        return response()->json(status: 204);
    }

    private function resolveTemplate(string $templateId): ProvisioningTemplate
    {
        $template = ProvisioningTemplate::query()->whereKey($templateId)->first();

        if ($template === null) {
            throw (new ModelNotFoundException())->setModel(ProvisioningTemplate::class, [$templateId]);
        }

        return $template;
    }
}
