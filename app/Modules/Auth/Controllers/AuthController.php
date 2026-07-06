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

    public function loginOptions(): JsonResponse
    {
        $roleOrder = [
            'admin' => 10,
            'driver' => 20,
            'warehouse_operator' => 30,
            'customer' => 40,
            'technician' => 50,
        ];
        $seededEmails = [
            'admin@example.com',
            'driver@example.com',
            'warehouse@example.com',
            'technician@example.com',
            'eva.vandijk@example.com',
            'amar.kovac@example.com',
            'lejla.hadzic@example.com',
            'eindhoven.parts@example.com',
            'rotterdam.fresh@example.com',
            'sarajevo.trade@example.com',
        ];

        $users = User::query()
            ->with(['role', 'customerDetail'])
            ->where('is_active', true)
            ->whereIn('email', $seededEmails)
            ->whereHas('role', fn ($query) => $query->whereIn('name', array_keys($roleOrder)))
            ->get()
            ->sort(function (User $left, User $right) use ($roleOrder): int {
                $roleComparison = ($roleOrder[$left->role?->name] ?? 999) <=> ($roleOrder[$right->role?->name] ?? 999);

                return $roleComparison !== 0 ? $roleComparison : $left->id <=> $right->id;
            })
            ->values();

        return $this->success(
            UserResource::collection($users)->resolve(),
            __('Login options retrieved successfully.'),
        );
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
        $tokenOnly = in_array(
            strtolower((string) $request->headers->get('X-Trackpal-Token-Only', '')),
            ['1', 'true', 'yes'],
            true,
        );
        $relations = $tokenOnly
            ? ['role', 'customerDetail']
            : ['role.rolePermissions.module', 'customerDetail'];

        $request->user()->loadMissing($relations);

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
