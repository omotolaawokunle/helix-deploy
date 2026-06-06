<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ChangePasswordAction;
use App\Modules\Auth\Actions\UpdateProfileAction;
use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\DTOs\ChangePasswordDTO;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\DTOs\RegisterDTO;
use App\Modules\Auth\DTOs\UpdateProfileDTO;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\UpdateProfileRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Resources\UserWithOrgResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(RegisterDTO::fromRequest($request));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse|UserWithOrgResource
    {
        try {
            $user = $this->authService->login(LoginDTO::fromRequest($request));
        } catch (InvalidCredentialsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        $request->session()->regenerate();

        return UserWithOrgResource::make($user);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    public function user(Request $request): UserWithOrgResource
    {
        $user = $request->user();

        return UserWithOrgResource::make($user?->load('currentOrganizationRelation'));
    }

    public function updateProfile(
        UpdateProfileRequest $request,
        UpdateProfileAction $action,
    ): UserWithOrgResource {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $updated = $action->execute($user, UpdateProfileDTO::fromRequest($request));

        return UserWithOrgResource::make($updated->load('currentOrganizationRelation'));
    }

    public function changePassword(
        ChangePasswordRequest $request,
        ChangePasswordAction $action,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $action->execute($user, ChangePasswordDTO::fromRequest($request));

        return response()->json(status: 204);
    }

    public function verifyEmail(Request $request, string $id, string $hash): UserWithOrgResource|JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ((string) $user->getKey() !== $id || ! hash_equals(sha1((string) $user->email), $hash)) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return UserWithOrgResource::make($user->load('currentOrganizationRelation'));
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(status: 204);
    }
}
