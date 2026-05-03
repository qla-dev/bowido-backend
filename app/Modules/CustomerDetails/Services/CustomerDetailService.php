<?php

namespace App\Modules\CustomerDetails\Services;

use App\Modules\CustomerDetails\DTOs\CustomerDetailData;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Repositories\CustomerDetailRepository;
use App\Modules\Shared\Services\BaseCrudService;

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
}
