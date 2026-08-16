<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DTOs\LoginData;
use App\Modules\Auth\DTOs\RegisterData;
use App\Modules\Auth\Models\ApiToken;
use App\Modules\Auth\Repositories\ApiTokenRepository;
use App\Modules\Auth\Support\AuthLoginLogger;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Users\Services\CredentialDeliveryService;
use App\Modules\Users\Support\TemporaryPasswordGenerator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthService
{
    private const SESSION_AUTH_TOKEN_KEY = 'auth_token';

    private const SESSION_AUTH_TOKEN_EXPIRES_AT_KEY = 'auth_token_expires_at';

    private const INVALID_LOGIN_MESSAGE = 'Email or password are incorrect.';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly TemporaryPasswordGenerator $passwordGenerator,
        private readonly CredentialDeliveryService $credentialDeliveryService,
    ) {}

    /**
     * @return array{token: string, token_type: string, expires_at: string|null, session_expires_at: string|null, user: User}
     */
    public function login(LoginData $data, Request $request): array
    {
        $traceId = (string) Str::uuid();
        $startedAt = microtime(true);

        $request->attributes->set('auth_login_trace_id', $traceId);

        AuthLoginLogger::info('Auth login started.', array_merge(
            $this->requestContext($request, $traceId),
            $this->loginDataContext($data),
        ));

        try {
            $user = $data->loginType === 'customer'
                ? $this->userRepository->findByKvkForAuth((string) $data->kvk, $data->customerDetailId)
                : $this->userRepository->findByEmailForAuth((string) $data->email);

            AuthLoginLogger::info('Auth login user lookup completed.', array_merge(
                $this->requestContext($request, $traceId),
                $this->loginDataContext($data),
                $this->userContext($user),
            ));

            if (! $user) {
                AuthLoginLogger::warning('Auth login failed: user not found.', array_merge(
                    $this->requestContext($request, $traceId),
                    $this->loginDataContext($data),
                ));

                throw new AuthenticationException(self::INVALID_LOGIN_MESSAGE);
            }

            if (! $user->is_active) {
                AuthLoginLogger::warning('Auth login failed: user inactive.', array_merge(
                    $this->requestContext($request, $traceId),
                    $this->userContext($user),
                ));

                throw new AuthenticationException(self::INVALID_LOGIN_MESSAGE);
            }

            if (! Hash::check($data->password, $user->password)) {
                AuthLoginLogger::warning('Auth login failed: password mismatch.', array_merge(
                    $this->requestContext($request, $traceId),
                    $this->userContext($user),
                ));

                throw new AuthenticationException(self::INVALID_LOGIN_MESSAGE);
            }

            AuthLoginLogger::info('Auth login credentials accepted.', array_merge(
                $this->requestContext($request, $traceId),
                $this->userContext($user),
            ));

            $result = $this->issueToken($user, $data->tokenName, $traceId);

            $shouldStartSession = $this->shouldStartSession($request);

            AuthLoginLogger::info('Auth login session decision made.', array_merge(
                $this->requestContext($request, $traceId),
                $this->userContext($result['user']),
                [
                    'should_start_session' => $shouldStartSession,
                    'has_session' => $request->hasSession(),
                ],
            ));

            if ($shouldStartSession) {
                $result['user']->loadMissing(['role.rolePermissions.module', 'customerDetail']);

                $this->startSession(
                    request: $request,
                    user: $result['user'],
                    plainTextToken: $result['token'],
                    tokenExpiresAt: $result['expires_at'],
                    traceId: $traceId,
                );
            }

            $sessionExpiresAt = $shouldStartSession ? $this->sessionExpiresAt() : null;

            AuthLoginLogger::info('Auth login completed.', array_merge(
                $this->requestContext($request, $traceId),
                $this->userContext($result['user']),
                [
                    'token_expires_at' => $result['expires_at'],
                    'session_expires_at' => $sessionExpiresAt,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ));

            return [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['expires_at'],
                'session_expires_at' => $sessionExpiresAt,
                'user' => $result['user'],
            ];
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            AuthLoginLogger::error('Auth login crashed.', array_merge(
                $this->requestContext($request, $traceId),
                $this->loginDataContext($data),
                $this->exceptionContext($exception),
                [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ));

            throw $exception;
        }
    }

    /** @return array{user: User, email_sent: bool} */
    public function register(RegisterData $data): array
    {
        $role = Role::query()
            ->whereKey($data->roleId)
            ->where('is_active', true)
            ->first();

        if (! $role instanceof Role) {
            throw ValidationException::withMessages([
                'role_id' => [__('Selected role is invalid or inactive.')],
            ]);
        }

        $temporaryPassword = $this->passwordGenerator->generate();
        $user = DB::transaction(function () use ($role, $data, $temporaryPassword): User {
            /** @var User $user */
            $user = $this->userRepository->create([
                'role_id' => $role->id,
                'name' => $data->name,
                'email' => $data->email,
                'phone_number' => $data->phoneNumber,
                'password' => $temporaryPassword,
                'first_time_login' => true,
                'is_active' => true,
            ]);

            $user->load(['role.rolePermissions.module', 'customerDetail']);

            return $user;
        });

        $emailSent = true;
        try {
            $this->credentialDeliveryService->send($user, $temporaryPassword);
        } catch (Throwable $exception) {
            $emailSent = false;
            report($exception);
        }

        return ['user' => $user, 'email_sent' => $emailSent];
    }

    public function logout(Request $request): void
    {
        $hashedTokens = [];

        /** @var ApiToken|null $currentApiToken */
        $currentApiToken = $request->attributes->get('currentApiToken');

        if ($currentApiToken instanceof ApiToken) {
            $hashedTokens[] = $currentApiToken->token;
        }

        $bearerToken = $request->bearerToken();

        if (is_string($bearerToken) && $bearerToken !== '') {
            $hashedTokens[] = hash('sha256', $bearerToken);
        }

        if ($request->hasSession()) {
            $hasSessionContext = Auth::guard('web')->check()
                || $request->session()->has(self::SESSION_AUTH_TOKEN_KEY)
                || $request->cookies->has((string) config('session.cookie'));

            if ($hasSessionContext) {
                $sessionToken = $request->session()->get(self::SESSION_AUTH_TOKEN_KEY);

                if (is_string($sessionToken) && $sessionToken !== '') {
                    $hashedTokens[] = hash('sha256', $sessionToken);
                }

                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        foreach (array_unique($hashedTokens) as $hashedToken) {
            $apiToken = $this->apiTokenRepository->findByHashedToken($hashedToken);

            if ($apiToken instanceof ApiToken) {
                $this->apiTokenRepository->delete($apiToken);
            }
        }
    }

    /**
     * @return array{token: string, expires_at: string|null, user: User}
     */
    private function issueToken(User $user, string $tokenName, string $traceId): array
    {
        AuthLoginLogger::info('Auth token issue started.', [
            'trace_id' => $traceId,
            'user_id' => $user->id,
            'token_name' => Str::limit($tokenName, 100),
            'token_ttl_minutes' => (int) env('API_TOKEN_TTL_MINUTES', config('session.lifetime')),
        ]);

        return DB::transaction(function () use ($user, $tokenName, $traceId): array {
            $plainTextToken = Str::random(80);
            $expiresAt = now()->addMinutes((int) env('API_TOKEN_TTL_MINUTES', config('session.lifetime')));

            $apiToken = $this->apiTokenRepository->create(
                user: $user,
                name: $tokenName,
                hashedToken: hash('sha256', $plainTextToken),
                expiresAt: $expiresAt,
            );

            $authenticatedUser = $this->markUserLoggedIn($user);

            AuthLoginLogger::info('Auth token issued.', [
                'trace_id' => $traceId,
                'user_id' => $authenticatedUser->id,
                'api_token_id' => $apiToken->id,
                'token_name' => Str::limit($tokenName, 100),
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'last_login_at' => $authenticatedUser->last_login_at?->toIso8601String(),
            ]);

            return [
                'token' => $plainTextToken,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'user' => $authenticatedUser,
            ];
        });
    }

    private function startSession(Request $request, User $user, string $plainTextToken, ?string $tokenExpiresAt, string $traceId): void
    {
        if (! $request->hasSession()) {
            AuthLoginLogger::warning('Auth login session was requested but request has no session store.', array_merge(
                $this->requestContext($request, $traceId),
                $this->userContext($user),
            ));

            return;
        }

        AuthLoginLogger::info('Auth login session start requested.', array_merge(
            $this->requestContext($request, $traceId),
            $this->userContext($user),
            [
                'session_id_exists_before_regenerate' => $request->session()->isStarted()
                    && (string) $request->session()->getId() !== '',
                'token_expires_at' => $tokenExpiresAt,
            ],
        ));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_AUTH_TOKEN_KEY, $plainTextToken);
        $request->session()->put(self::SESSION_AUTH_TOKEN_EXPIRES_AT_KEY, $tokenExpiresAt);

        AuthLoginLogger::info('Auth login session started.', array_merge(
            $this->requestContext($request, $traceId),
            $this->userContext($user),
            [
                'session_started' => $request->session()->isStarted(),
                'session_id_present' => (string) $request->session()->getId() !== '',
                'session_has_auth_token' => $request->session()->has(self::SESSION_AUTH_TOKEN_KEY),
                'session_has_user_key' => $request->session()->has(Auth::guard('web')->getName()),
                'token_expires_at' => $tokenExpiresAt,
            ],
        ));
    }

    private function markUserLoggedIn(User $user): User
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->loadMissing(['role', 'customerDetail']);
    }

    private function shouldStartSession(Request $request): bool
    {
        $tokenOnly = strtolower((string) $request->headers->get('X-Trackpal-Token-Only', ''));

        return ! in_array($tokenOnly, ['1', 'true', 'yes'], true);
    }

    private function sessionExpiresAt(): string
    {
        return now()->addMinutes((int) config('session.lifetime'))->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Request $request, string $traceId): array
    {
        return [
            'trace_id' => $traceId,
            'path' => $request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'content_type' => $request->headers->get('content-type'),
            'accept' => $request->headers->get('accept'),
            'origin' => $request->headers->get('origin'),
            'referer' => $request->headers->get('referer'),
            'user_agent' => Str::limit((string) $request->userAgent(), 200),
            'token_only_header' => $request->headers->get('X-Trackpal-Token-Only'),
            'session_driver' => config('session.driver'),
            'session_cookie' => config('session.cookie'),
            'session_domain' => config('session.domain'),
            'session_secure' => config('session.secure'),
            'session_same_site' => config('session.same_site'),
            'session_lifetime' => config('session.lifetime'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loginDataContext(LoginData $data): array
    {
        return [
            'login_type' => $data->loginType,
            'identifier_type' => $data->loginType === 'customer' ? 'kvk' : 'email',
            'has_email' => is_string($data->email) && trim($data->email) !== '',
            'email_domain' => $this->emailDomain((string) $data->email),
            'email_hash' => $this->hashedValue((string) $data->email),
            'has_kvk' => is_string($data->kvk) && trim($data->kvk) !== '',
            'kvk_hash' => $this->hashedValue($this->normalizedKvk((string) $data->kvk)),
            'has_customer_selection' => $data->customerDetailId !== null,
            'token_name' => Str::limit($data->tokenName, 100),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userContext(?User $user): array
    {
        if (! $user) {
            return [
                'user_found' => false,
            ];
        }

        $passwordInfo = password_get_info((string) $user->password);

        return [
            'user_found' => true,
            'user_id' => $user->id,
            'user_active' => (bool) $user->is_active,
            'role_id' => $user->role_id,
            'role_name' => $user->role?->name,
            'role_active' => $user->role ? (bool) $user->role->is_active : null,
            'customer_detail_id' => $user->customerDetail?->id,
            'customer_detail_active' => $user->customerDetail ? (bool) $user->customerDetail->is_active : null,
            'password_hash_present' => (string) $user->password !== '',
            'password_hash_algo' => $passwordInfo['algoName'] ?? 'unknown',
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

        if (! str_contains($email, '@')) {
            return null;
        }

        return Str::afterLast($email, '@');
    }

    private function hashedValue(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : hash('sha256', strtolower($value));
    }

    private function normalizedKvk(string $kvk): string
    {
        return strtolower((string) preg_replace('/[\s.-]+/', '', trim($kvk)));
    }
}
