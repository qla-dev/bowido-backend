<?php

namespace App\Modules\Users\Services;

use App\Modules\CustomerDetails\DTOs\CustomerDetailData;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Repositories\CustomerDetailRepository;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\DTOs\UserData;
use App\Modules\Users\Models\User;
use App\Modules\Users\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;

class UserService extends BaseCrudService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CustomerDetailRepository $customerDetailRepository,
    ) {
        parent::__construct($userRepository);
    }

    public function create(UserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = $this->userRepository->create($data->toArray());

            $this->syncCustomerDetails($user, $data);

            return $user->refresh()->load(['role', 'customerDetail']);
        });
    }

    public function update(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            /** @var User $updatedUser */
            $updatedUser = $this->userRepository->update($user, $data->toArray());

            $this->syncCustomerDetails($updatedUser, $data);

            return $updatedUser->refresh()->load(['role', 'customerDetail']);
        });
    }

    private function syncCustomerDetails(User $user, UserData $data): void
    {
        if (! is_array($data->customerDetails)) {
            return;
        }

        $customerDetailData = CustomerDetailData::fromArray([
            ...$data->customerDetails,
            'user_id' => $user->id,
        ]);

        $existingCustomerDetail = $this->customerDetailRepository->findByUserId($user->id);

        if ($existingCustomerDetail instanceof CustomerDetail) {
            $this->customerDetailRepository->update($existingCustomerDetail, $customerDetailData->toArray());

            return;
        }

        $this->customerDetailRepository->create($customerDetailData->toArray());
    }
}
