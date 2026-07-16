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
use App\Modules\CustomerDetails\Models\CustomerDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

    public function kvkLookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['kvk' => ['required', 'string', 'max:255']]);
        $kvk = preg_replace('/[\s.-]+/', '', trim($validated['kvk']));
        $detail = CustomerDetail::query()->with('user')->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$kvk])->first();
        if (! $detail) throw ValidationException::withMessages(['kvk' => [__('KVK number was not found.')]]);
        return $this->success(['company_name' => $detail->company_name, 'kvk' => $detail->kvk, 'email' => $detail->billing_email, 'phone_number' => $detail->user?->phone_number, 'fixed_phone' => $detail->fixed_phone, 'billing_address' => $detail->billing_address, 'delivery_address' => $detail->delivery_address], __('Customer details found.'));
    }

    public function registerByKvk(Request $request): JsonResponse
    {
        $data = $request->validate(['kvk' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'billing_address' => ['required', 'string', 'max:1000'], 'delivery_address' => ['nullable', 'string', 'max:1000'], 'phone_number' => ['nullable', 'string', 'max:255'], 'fixed_phone' => ['nullable', 'string', 'max:50'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $kvk = preg_replace('/[\s.-]+/', '', trim($data['kvk']));
        $user = DB::transaction(function () use ($data, $kvk): User {
            $detail = CustomerDetail::query()->with('user')->lockForUpdate()->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$kvk])->first();
            if (! $detail) throw ValidationException::withMessages(['kvk' => [__('KVK number was not found.')]]);
            $emailTaken = User::query()->where('email', strtolower($data['email']))->whereKeyNot($detail->user_id)->exists();
            if ($emailTaken) throw ValidationException::withMessages(['email' => [__('This email address is already in use.')]]);
            $phoneTaken = filled($data['phone_number'] ?? null) && User::query()->where('phone_number', $data['phone_number'])->whereKeyNot($detail->user_id)->exists();
            if ($phoneTaken) throw ValidationException::withMessages(['phone_number' => [__('This phone number is already in use.')]]);
            $detail->user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone_number' => $data['phone_number'] ?: null, 'password' => Hash::make($data['password']), 'is_active' => true]);
            $detail->update(['company_name' => $data['name'], 'billing_email' => strtolower($data['email']), 'fixed_phone' => $data['fixed_phone'] ?: null, 'billing_address' => $data['billing_address'], 'delivery_address' => $data['delivery_address'] ?: null, 'is_active' => true]);
            return $detail->user->fresh(['role', 'customerDetail']);
        });
        return $this->success((new UserResource($user))->resolve(), __('Registration successful.'), status: 201);
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
