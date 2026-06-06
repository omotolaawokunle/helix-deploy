<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\CreateApiTokenAction;
use App\Modules\Auth\Actions\RevokeApiTokenAction;
use App\Modules\Auth\Enums\ApiTokenAbility;
use App\Modules\Auth\Requests\StoreApiTokenRequest;
use App\Modules\Auth\Resources\ApiTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        return ApiTokenResource::collection($tokens);
    }

    public function store(
        StoreApiTokenRequest $request,
        CreateApiTokenAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $ability = ApiTokenAbility::from((string) $request->input('ability'));
        $token = $action->execute($user, (string) $request->input('name'), $ability);

        return response()->json([
            'data' => ApiTokenResource::make($token->accessToken),
            'plainTextToken' => $token->plainTextToken,
        ], 201);
    }

    public function destroy(string $token, Request $request, RevokeApiTokenAction $action): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $accessToken = PersonalAccessToken::query()
            ->whereKey($token)
            ->where('tokenable_id', (string) $user->getKey())
            ->where('tokenable_type', $user->getMorphClass())
            ->firstOrFail();

        $action->execute($user, $accessToken);

        return response()->json(status: 204);
    }
}
