<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Exceptions\KvkLookupException;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\CompleteFirstLoginRequest;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\KvkLookupRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\KvkCompanyLookupService;
use App\Modules\Auth\Support\AuthRegistrationLogger;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Support\CustomerImportExceptions;
use App\Modules\Roles\Models\Role;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Users\Models\User;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\CredentialDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly KvkCompanyLookupService $kvkCompanyLookupService,
        private readonly CredentialDeliveryService $credentialDeliveryService,
    ) {}

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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->validated('email')));
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();
        $sent = false;

        if ($user instanceof User) {
            $result = $this->credentialDeliveryService->resetAndSend([$user->id]);
            $sent = count($result['sent']) === 1;
        }

        Log::info('Password reset request processed.', [
            'email_hash' => hash('sha256', $email),
            'email_domain' => Str::contains($email, '@') ? Str::afterLast($email, '@') : null,
            'user_found' => $user instanceof User,
            'password_email_sent' => $sent,
            'ip' => $request->ip(),
        ]);

        // Use the same response for every request to avoid account enumeration.
        return $this->success(
            null,
            __('If an active account matches this email address, a new temporary password has been sent.'),
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
        $traceId = (string) Str::uuid();
        $request->attributes->set('auth_registration_trace_id', $traceId);

        $request->merge([
            'kvk' => preg_replace('/[\s.\-\/()]+/', '', trim((string) $request->input('kvk'))),
        ]);

        AuthRegistrationLogger::info('Auth KVK registration started.', $this->registrationRequestContext($request, $traceId, 'kvk_customer'));

        try {
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
            $data += array_fill_keys([
                'country', 'phone_number', 'fixed_phone', 'street', 'house_number', 'postal_code', 'city',
                'warehouse1_street', 'warehouse1_house_number', 'warehouse1_postal_code', 'warehouse1_city',
                'warehouse2_street', 'warehouse2_house_number', 'warehouse2_postal_code', 'warehouse2_city',
            ], null);
            $kvk = preg_replace('/[\s.-]+/', '', trim($data['kvk']));
            $hasLocalCustomer = CustomerDetail::query()
                ->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$kvk])
                ->exists();

            // Imported customers can register from their local record. For a
            // new customer, verify the KVK number with the authoritative
            // source before creating that local record.
            $kvkLookup = $hasLocalCustomer ? null : $this->kvkCompanyLookupService->lookup($kvk);
            if ($kvkLookup !== null && $kvkLookup['source'] === 'not_found') {
                throw ValidationException::withMessages(['kvk' => [__('KVK number was not found.')]]);
            }

            $user = DB::transaction(function () use ($data, $kvk, $traceId): User {
                $details = CustomerDetail::query()
                    ->with('user')
                    ->lockForUpdate()
                    ->whereRaw("replace(replace(replace(kvk, ' ', ''), '.', ''), '-', '') = ?", [$kvk])
                    ->get();
                AuthRegistrationLogger::info('Auth KVK registration customer lookup completed.', [
                    'trace_id' => $traceId,
                    'registration_type' => 'kvk_customer',
                    'kvk_hash' => $this->hashedValue($kvk),
                    'matching_customer_details' => $details->count(),
                ]);
                $detail = null;
                $existingUserId = null;
                if ($details->isNotEmpty()) {
                    $selectedName = mb_strtolower(trim($data['name']));
                    $detail = $details->first(
                        fn (CustomerDetail $candidate): bool => mb_strtolower(trim((string) $candidate->company_name)) === $selectedName,
                    );
                    if ($detail === null && $details->count() > 1) {
                        AuthRegistrationLogger::warning('Auth KVK registration failed: company selection required.', [
                            'trace_id' => $traceId,
                            'registration_type' => 'kvk_customer',
                            'kvk_hash' => $this->hashedValue($kvk),
                            'matching_customer_details' => $details->count(),
                        ]);
                        throw ValidationException::withMessages(['name' => [__('Choose a company name associated with this KVK number.')]]);
                    }
                    $detail ??= $details->first();
                    $existingUserId = $detail->user_id;
                }
                $emailTaken = ! CustomerImportExceptions::allowsSharedEmail($data['email'])
                    && User::query()->where('email', strtolower($data['email']))->when($existingUserId, fn ($query) => $query->whereKeyNot($existingUserId))->exists();
                if ($emailTaken) {
                    AuthRegistrationLogger::warning('Auth KVK registration failed: email already in use.', [
                        'trace_id' => $traceId,
                        'registration_type' => 'kvk_customer',
                        'customer_detail_id' => $detail?->id,
                        'email_hash' => $this->hashedValue($data['email']),
                    ]);
                    throw ValidationException::withMessages(['email' => [__('This email address is already in use.')]]);
                }
                $phoneTaken = filled($data['phone_number'] ?? null) && User::query()->where('phone_number', $data['phone_number'])->when($existingUserId, fn ($query) => $query->whereKeyNot($existingUserId))->exists();
                if ($phoneTaken) {
                    AuthRegistrationLogger::warning('Auth KVK registration failed: phone number already in use.', [
                        'trace_id' => $traceId,
                        'registration_type' => 'kvk_customer',
                        'customer_detail_id' => $detail?->id,
                    ]);
                    throw ValidationException::withMessages(['phone_number' => [__('This phone number is already in use.')]]);
                }
                if ($detail === null) {
                    $customerRoleId = Role::query()->where('name', 'customer')->value('id');
                    if ($customerRoleId === null) {
                        throw new \RuntimeException('Customer role is not configured.');
                    }

                    $user = User::query()->create([
                        'role_id' => $customerRoleId,
                        'name' => $data['name'],
                        'email' => strtolower($data['email']),
                        'phone_number' => $data['phone_number'] ?: null,
                        'password' => Hash::make($data['password']),
                        'is_active' => true,
                    ]);
                    $detail = CustomerDetail::query()->create([
                        'user_id' => $user->id,
                        'company_name' => $data['name'],
                        'kvk' => $kvk,
                    ]);

                    AuthRegistrationLogger::info('Auth KVK registration customer record created.', [
                        'trace_id' => $traceId,
                        'registration_type' => 'kvk_customer',
                        'user_id' => $user->id,
                        'customer_detail_id' => $detail->id,
                    ]);
                } else {
                    $user = $detail->user;
                }

                AuthRegistrationLogger::info('Auth KVK registration user update started.', [
                    'trace_id' => $traceId,
                    'registration_type' => 'kvk_customer',
                    'user_id' => $user?->id,
                    'customer_detail_id' => $detail->id,
                ]);
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

                AuthRegistrationLogger::info('Auth KVK registration customer record updated.', [
                    'trace_id' => $traceId,
                    'registration_type' => 'kvk_customer',
                    'user_id' => $user->id,
                    'customer_detail_id' => $detail->id,
                ]);

                return $user->fresh(['role', 'customerDetail']);
            });
        } catch (KvkLookupException $exception) {
            AuthRegistrationLogger::warning('Auth KVK registration lookup unavailable.', array_merge(
                $this->registrationRequestContext($request, $traceId, 'kvk_customer'),
                ['response_status' => $exception->httpStatus],
            ));

            return response()->json([
                'message' => $exception->getMessage(),
                'data' => null,
                'meta' => [],
                'errors' => [],
            ], $exception->httpStatus);
        } catch (ValidationException $exception) {
            AuthRegistrationLogger::warning('Auth KVK registration rejected.', array_merge(
                $this->registrationRequestContext($request, $traceId, 'kvk_customer'),
                ['error_fields' => array_keys($exception->errors()), 'errors' => $exception->errors()],
            ));

            throw $exception;
        } catch (Throwable $exception) {
            AuthRegistrationLogger::error('Auth KVK registration crashed.', array_merge(
                $this->registrationRequestContext($request, $traceId, 'kvk_customer'),
                $this->exceptionContext($exception),
            ));

            throw $exception;
        }

        AuthRegistrationLogger::info('Auth KVK registration completed.', [
            'trace_id' => $traceId,
            'registration_type' => 'kvk_customer',
            'user_id' => $user->id,
            'customer_detail_id' => $user->customerDetail?->id,
        ]);

        return $this->success((new UserResource($user))->resolve(), __('Registration successful.'), status: 201);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $traceId = (string) ($request->attributes->get('auth_registration_trace_id') ?: Str::uuid());
        $request->attributes->set('auth_registration_trace_id', $traceId);
        $data = $request->validated();

        AuthRegistrationLogger::info('Auth registration started.', $this->registrationRequestContext($request, $traceId, 'staff_user'));

        try {
            $result = $this->authService->register(RegisterData::fromArray($data));
            $user = $result['user'];
        } catch (ValidationException $exception) {
            AuthRegistrationLogger::warning('Auth registration rejected.', array_merge(
                $this->registrationRequestContext($request, $traceId, 'staff_user'),
                ['error_fields' => array_keys($exception->errors()), 'errors' => $exception->errors()],
            ));

            throw $exception;
        } catch (Throwable $exception) {
            AuthRegistrationLogger::error('Auth registration crashed.', array_merge(
                $this->registrationRequestContext($request, $traceId, 'staff_user'),
                $this->exceptionContext($exception),
            ));

            throw $exception;
        }

        AuthRegistrationLogger::info('Auth registration completed.', [
            'trace_id' => $traceId,
            'registration_type' => 'staff_user',
            'user_id' => $user->id,
            'role_id' => $user->role_id,
        ]);

        $responseData = (new UserResource($user))->resolve();
        $responseData['credential_email_sent'] = $result['email_sent'];
        $responseData['credential_email_warning'] = $result['email_sent']
            ? null
            : __('The user was created, but the login details email could not be sent. Use Send login details to try again.');

        return $this->success(
            $responseData,
            $result['email_sent'] ? __('Registration successful.') : __('User created, but login details could not be sent.'),
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

    public function completeFirstLoginWithPassword(CompleteFirstLoginRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->first_time_login) {
            throw ValidationException::withMessages([
                'first_time_login' => [__('First-time login has already been completed.')],
            ]);
        }

        $updatedUser = DB::transaction(function () use ($user, $request): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update([
                'password' => $request->validated('password'),
                'first_time_login' => false,
            ]);

            return $lockedUser->fresh(['role.rolePermissions.module', 'customerDetail']);
        });

        return $this->success(
            (new UserResource($updatedUser))->resolve(),
            __('Password changed successfully.'),
        );
    }

    public function keepFirstLoginPassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->first_time_login) {
            throw ValidationException::withMessages([
                'first_time_login' => [__('First-time login has already been completed.')],
            ]);
        }

        $updatedUser = DB::transaction(function () use ($user): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update(['first_time_login' => false]);

            return $lockedUser->fresh(['role.rolePermissions.module', 'customerDetail']);
        });

        return $this->success(
            (new UserResource($updatedUser))->resolve(),
            __('Assigned password retained successfully.'),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return $this->success(null, __('Logout successful.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationRequestContext(Request $request, string $traceId, string $registrationType): array
    {
        $email = (string) $request->input('email');
        $kvk = (string) preg_replace('/[\s.\-\/()]+/', '', trim((string) $request->input('kvk')));

        return [
            'trace_id' => $traceId,
            'registration_type' => $registrationType,
            'path' => $request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'content_type' => $request->headers->get('content-type'),
            'accept' => $request->headers->get('accept'),
            'origin' => $request->headers->get('origin'),
            'referer' => $request->headers->get('referer'),
            'user_agent' => Str::limit((string) $request->userAgent(), 200),
            'email_domain' => $this->emailDomain($email),
            'email_hash' => $this->hashedValue($email),
            'kvk_hash' => $this->hashedValue($kvk),
            'role_id' => $request->input('role_id'),
            'has_phone_number' => $request->filled('phone_number'),
            'has_password' => $request->filled('password'),
            'has_password_confirmation' => $request->filled('password_confirmation'),
            'submitted_fields' => array_values(array_diff(array_keys($request->all()), ['password', 'password_confirmation'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionContext(Throwable $exception): array
    {
        return [
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
        ];
    }

    private function emailDomain(string $email): ?string
    {
        $email = strtolower(trim($email));

        return str_contains($email, '@') ? Str::afterLast($email, '@') : null;
    }

    private function hashedValue(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : hash('sha256', strtolower($value));
    }
}
