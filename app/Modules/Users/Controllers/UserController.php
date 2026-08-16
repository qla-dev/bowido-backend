<?php

namespace App\Modules\Users\Controllers;

use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Users\DTOs\UserData;
use App\Modules\Users\Models\User;
use App\Modules\Users\Requests\ListUsersRequest;
use App\Modules\Users\Requests\SendLoginDetailsRequest;
use App\Modules\Users\Requests\StoreUserRequest;
use App\Modules\Users\Requests\UpdateUserRequest;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\CredentialDeliveryService;
use App\Modules\Users\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends ApiController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly CredentialDeliveryService $credentialDeliveryService,
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return $this->successCollection(
            $this->userService->paginate(ListQueryData::fromRequest($request), $request->user()),
            UserResource::class,
            __('Users retrieved successfully.'),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        Log::info('Client creation payload accepted for processing.', [
            'payload' => $request->except(['password', 'password_confirmation']),
        ]);

        $created = $this->userService->createWithTemporaryPassword(UserData::fromArray($request->validated()));
        $user = $created['user'];
        $emailSent = true;

        try {
            $this->credentialDeliveryService->send($user, $created['temporary_password']);
        } catch (Throwable $exception) {
            $emailSent = false;
            Log::warning('User created but credential email could not be sent.', [
                'user_id' => $user->id,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }

        Log::info('Client user and customer details created.', [
            'user_id' => $user->id,
            'customer_detail_id' => $user->customerDetail?->id,
            'email' => $user->email,
        ]);

        $data = (new UserResource($user))->resolve();
        $data['credential_email_sent'] = $emailSent;
        $data['credential_email_warning'] = $emailSent
            ? null
            : __('The user was created, but the login details email could not be sent. Use Send login details to try again.');

        return $this->success(
            $data,
            $emailSent
                ? __('User created successfully.')
                : __('User created, but login details could not be sent.'),
            status: 201,
        );
    }

    public function sendLoginDetails(SendLoginDetailsRequest $request): JsonResponse
    {
        $this->authorize('distributeCredentials', User::class);

        $result = $this->credentialDeliveryService->resetAndSend($request->validated('user_ids'));

        return $this->success(
            $result,
            count($result['failed']) === 0
                ? __('Login details sent successfully.')
                : __('Some login details could not be sent.'),
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->successItem(
            $this->userService->find($user->id, request()->user()),
            UserResource::class,
            __('User retrieved successfully.'),
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updatedUser = $this->userService->update($user, UserData::fromArray([
            ...$user->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedUser, UserResource::class, __('User updated successfully.'));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->deleteUserAndDetachRecords($user);

        return $this->success(null, __('User deleted successfully.'));
    }
}
