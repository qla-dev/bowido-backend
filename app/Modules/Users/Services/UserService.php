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
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

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
        try {
            return DB::transaction(function () use ($data): User {
                /** @var User $user */
                $user = $this->userRepository->create($data->toArray());

                $customerDetail = $this->syncCustomerDetails($user, $data);

                if (
                    $customerDetail !== null &&
                    (int) $customerDetail->user_id !== (int) $user->id
                ) {
                    throw new LogicException('Created client details are not linked to the new user.');
                }

                return $user->refresh()->load(['role.rolePermissions.module', 'customerDetail']);
            });
        } catch (Throwable $exception) {
            if (is_array($data->customerDetails)) {
                Log::error('Client creation failed.', [
                    'company_name' => $data->customerDetails['company_name'] ?? null,
                    'kvk' => $data->customerDetails['kvk'] ?? null,
                    'email' => $data->email,
                    'role_id' => $data->roleId,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'previous_exception_class' => $exception->getPrevious() ? $exception->getPrevious()::class : null,
                    'previous_exception_message' => $exception->getPrevious()?->getMessage(),
                    'exception' => $exception,
                ]);
            }

            throw $exception;
        }
    }

    public function update(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            /** @var User $updatedUser */
            $updatedUser = $this->userRepository->update($user, $data->toArray());

            $this->syncCustomerDetails($updatedUser, $data);

            return $updatedUser->refresh()->load(['role.rolePermissions.module', 'customerDetail']);
        });
    }

    public function deleteUserAndDetachRecords(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $userId = $user->id;

            DB::table('pallets')->where('user_id', $userId)->update(['user_id' => null]);
            DB::table('invoices')->where('user_id', $userId)->update(['user_id' => null]);
            DB::table('ghost_pallet_reports')->where('user_id', $userId)->update(['user_id' => null]);
            DB::table('pallet_photos')->where('client_id', $userId)->update(['client_id' => null]);
            DB::table('pallet_photos')->where('uploaded_by_user_id', $userId)->delete();
            DB::table('service_reports')->where('reported_by_user_id', $userId)->delete();
            DB::table('calendar_notes')->where('created_by_user_id', $userId)->delete();

            $user->delete();
        });
    }

    private function syncCustomerDetails(User $user, UserData $data): ?CustomerDetail
    {
        if (! is_array($data->customerDetails)) {
            return null;
        }

        $customerDetailData = CustomerDetailData::fromArray([
            ...$data->customerDetails,
            'user_id' => $user->id,
        ]);

        $existingCustomerDetail = $this->customerDetailRepository->findByUserId($user->id);

        if ($existingCustomerDetail instanceof CustomerDetail) {
            /** @var CustomerDetail $updatedCustomerDetail */
            $updatedCustomerDetail = $this->customerDetailRepository->update($existingCustomerDetail, $customerDetailData->toArray());

            return $updatedCustomerDetail;
        }

        /** @var CustomerDetail $createdCustomerDetail */
        $createdCustomerDetail = $this->customerDetailRepository->create($customerDetailData->toArray());

        return $createdCustomerDetail;
    }
}
