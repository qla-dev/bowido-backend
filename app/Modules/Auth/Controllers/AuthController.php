<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Exceptions\KvkLookupException;
use App\Modules\Auth\Requests\KvkLookupRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\KvkCompanyLookupService;
use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
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
    public function __construct(
        private readonly AuthService $authService,
        private readonly KvkCompanyLookupService $kvkCompanyLookupService,
    )
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $loginData = LoginData::fromArray($request->validated());

        if ($loginData->loginType === 'customer' && $loginData->customerDetailId === null) {
            $normalizedKvk = preg_replace('/[\s.-]+/', '', trim((string) $loginData->kvk));
            $companies = CustomerDetail::query()
                ->where('customer_details.is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->whereRaw(
                    "lower(replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '')) = ?",
                    [strtolower((string) $normalizedKvk)],
                )
                ->orderBy('company_name')
                ->get(['id', 'company_name']);

            if ($companies->count() > 1) {
                return $this->success(
                    data: [
                        'code' => 'company_selection_required',
                        'companies' => $companies->map(fn (CustomerDetail $detail) => [
                            'customer_detail_id' => $detail->id,
                            'company_name' => $detail->company_name,
                        ])->values(),
                    ],
                    message: __('Choose your company to continue.'),
                    status: 409,
                );
            }
        }

        $result = $this->authService->login($loginData, $request);

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

    public function kvkLookup(KvkLookupRequest $request): JsonResponse
    {
        $kvk = $request->validated('kvk');

        try {
            $result = $this->kvkCompanyLookupService->lookup($kvk);
        } catch (KvkLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => [],
                'errors' => [],
            ], $exception->httpStatus);
        }

        if ($result['source'] === 'not_found') {
            return response()->json([
                'message' => __('No company was found for this KVK number.'),
                'data' => ['source' => 'kvk', 'fields' => []],
                'meta' => [],
                'errors' => [],
            ], 404);
        }

        return $this->success([
            'source' => $result['source'],
            'fields' => $result['fields'],
            'company_names' => $result['company_names'],
            'company_options' => $result['company_options'],
        ], __('Customer details found.'));
    }

    public function registerByKvk(Request $request): JsonResponse
    {
        $request->merge([
            'kvk' => preg_replace('/[\s.\-\/()]+/', '', trim((string) $request->input('kvk'))),
        ]);

        $data = $request->validate([
            'kvk' => ['required', 'string', 'regex:/^\d{8}$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'fixed_phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:255'],
            'warehouse1_street' => ['nullable', 'string', 'max:255'],
            'warehouse1_house_number' => ['nullable', 'string', 'max:255'],
            'warehouse1_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse1_city' => ['nullable', 'string', 'max:255'],
            'warehouse2_street' => ['nullable', 'string', 'max:255'],
            'warehouse2_house_number' => ['nullable', 'string', 'max:255'],
            'warehouse2_postal_code' => ['nullable', 'string', 'max:32'],
            'warehouse2_city' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $kvk = preg_replace('/[\s.-]+/', '', trim($data['kvk']));
        $user = DB::transaction(function () use ($data, $kvk): User {
            $details = CustomerDetail::query()
                ->with('user')
                ->lockForUpdate()
                ->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$kvk])
                ->get();
            if ($details->isEmpty()) throw ValidationException::withMessages(['kvk' => [__('KVK number was not found.')]]);

            $selectedName = mb_strtolower(trim($data['name']));
            $detail = $details->first(
                fn (CustomerDetail $candidate): bool => mb_strtolower(trim((string) $candidate->company_name)) === $selectedName,
            );
            if ($detail === null && $details->count() > 1) {
                throw ValidationException::withMessages(['name' => [__('Choose a company name associated with this KVK number.')]]);
            }
            $detail ??= $details->first();
            $existingUserId = $detail->user_id;
            $emailTaken = ! CustomerImportExceptions::allowsSharedEmail($data['email'])
                && User::query()->where('email', strtolower($data['email']))->when($existingUserId, fn ($query) => $query->whereKeyNot($existingUserId))->exists();
            if ($emailTaken) throw ValidationException::withMessages(['email' => [__('This email address is already in use.')]]);
            $phoneTaken = filled($data['phone_number'] ?? null) && User::query()->where('phone_number', $data['phone_number'])->when($existingUserId, fn ($query) => $query->whereKeyNot($existingUserId))->exists();
            if ($phoneTaken) throw ValidationException::withMessages(['phone_number' => [__('This phone number is already in use.')]]);
            $user = $detail->user;
            $user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone_number' => $data['phone_number'] ?: null, 'password' => Hash::make($data['password']), 'is_active' => true]);

            $detail->fill([
                'company_name' => $data['name'],
                'country' => $data['country'] ?: null,
                'billing_email' => strtolower($data['email']),
                'fixed_phone' => $data['fixed_phone'] ?: null,
                'street' => $data['street'] ?: null,
                'house_number' => $data['house_number'] ?: null,
                'postal_code' => $data['postal_code'] ?: null,
                'city' => $data['city'] ?: null,
                'warehouse1_street' => $data['warehouse1_street'] ?: null,
                'warehouse1_house_number' => $data['warehouse1_house_number'] ?: null,
                'warehouse1_postal_code' => $data['warehouse1_postal_code'] ?: null,
                'warehouse1_city' => $data['warehouse1_city'] ?: null,
                'warehouse2_street' => $data['warehouse2_street'] ?: null,
                'warehouse2_house_number' => $data['warehouse2_house_number'] ?: null,
                'warehouse2_postal_code' => $data['warehouse2_postal_code'] ?: null,
                'warehouse2_city' => $data['warehouse2_city'] ?: null,
                'is_active' => true,
            ])->save();

            return $user->fresh(['role', 'customerDetail']);
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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return $this->success(null, __('Password changed successfully.'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return $this->success(null, __('Logout successful.'));
    }
}
