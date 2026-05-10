<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Users\Models\User;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(LoginData::fromArray($request->validated()), $request);

        return $this->success([
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_at' => $result['expires_at'],
            'session_expires_at' => $result['session_expires_at'],
            'user' => (new UserResource($result['user']))->resolve(),
        ], __('Login successful.'));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->authService->register(RegisterData::fromArray($request->validated()));

        return $this->success(
            (new UserResource($user))->resolve(),
            __('Registration successful.'),
            status: 201,
        );
    }

    public function me(Request $request): JsonResponse
    {
        $request->user()->loadMissing(['role.rolePermissions.module', 'customerDetail']);

        return $this->success(
            (new UserResource($request->user()))->resolve(),
            __('Authenticated user retrieved successfully.'),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return $this->success(null, __('Logout successful.'));
    }
}
