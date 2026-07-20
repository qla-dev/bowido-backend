<?php

namespace App\Modules\Users\Controllers;

use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Users\DTOs\UserData;
use App\Modules\Users\Models\User;
use App\Modules\Users\Requests\ListUsersRequest;
use App\Modules\Users\Requests\StoreUserRequest;
use App\Modules\Users\Requests\UpdateUserRequest;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserController extends ApiController
{
    public function __construct(private readonly UserService $userService)
    {
    }

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

        $user = $this->userService->create(UserData::fromArray($request->validated()));

        Log::info('Client user and customer details created.', [
            'user_id' => $user->id,
            'customer_detail_id' => $user->customerDetail?->id,
            'email' => $user->email,
        ]);

        return $this->successItem($user, UserResource::class, __('User created successfully.'), 201);
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
