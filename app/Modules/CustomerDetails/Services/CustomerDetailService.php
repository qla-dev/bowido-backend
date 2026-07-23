<?php

namespace App\Modules\CustomerDetails\Services;

use App\Modules\CustomerDetails\DTOs\CustomerDetailData;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Repositories\CustomerDetailRepository;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerDetailService extends BaseCrudService
{
    public function __construct(private readonly CustomerDetailRepository $customerDetailRepository)
    {
        parent::__construct($customerDetailRepository);
    }

    public function create(CustomerDetailData $data): CustomerDetail
    {
        /** @var CustomerDetail $customerDetail */
        $customerDetail = $this->customerDetailRepository->create($data->toArray());

        return $customerDetail->load('user.role');
    }

    public function update(CustomerDetail $customerDetail, CustomerDetailData $data): CustomerDetail
    {
        /** @var CustomerDetail $updatedCustomerDetail */
        $updatedCustomerDetail = $this->customerDetailRepository->update($customerDetail, $data->toArray());

        return $updatedCustomerDetail->load('user.role');
    }

    public function deleteClientAndUser(CustomerDetail $customerDetail): void
    {
        DB::transaction(function () use ($customerDetail): void {
            $userId = $customerDetail->user_id;
            $clientName = $customerDetail->company_name ?: $customerDetail->user?->name;

            // Keep operational history and show the former client in pallet tracking.
            Pallet::query()
                ->where('user_id', $userId)
                ->eachById(function (Pallet $pallet) use ($clientName): void {
                    $metadata = is_array($pallet->metadata) ? $pallet->metadata : [];
                    $metadata['deleted_client_name'] = $clientName;

                    $pallet->update([
                        'user_id' => null,
                        'metadata' => $metadata,
                    ]);
                });
            DB::table('invoices')->where('user_id', $userId)->update(['user_id' => null]);
            DB::table('ghost_pallet_reports')->where('user_id', $userId)->update(['user_id' => null]);
            DB::table('pallet_photos')->where('client_id', $userId)->update(['client_id' => null]);

            // These records cannot retain a deleted creator because their foreign keys are restrictive.
            DB::table('pallet_photos')->where('uploaded_by_user_id', $userId)->delete();
            DB::table('service_reports')->where('reported_by_user_id', $userId)->delete();
            DB::table('calendar_notes')->where('created_by_user_id', $userId)->delete();

            User::query()->whereKey($userId)->delete();
        });
    }
}
