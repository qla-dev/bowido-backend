<?php

namespace App\Modules\CustomerDetails\Controllers;

use App\Modules\CustomerDetails\DTOs\CustomerDetailData;
use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\CustomerDetails\Requests\ListCustomerDetailsRequest;
use App\Modules\CustomerDetails\Requests\StoreCustomerDetailRequest;
use App\Modules\CustomerDetails\Requests\UpdateCustomerDetailRequest;
use App\Modules\CustomerDetails\Resources\CustomerDetailResource;
use App\Modules\CustomerDetails\Services\CustomerDetailService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class CustomerDetailController extends ApiController
{
    public function __construct(private readonly CustomerDetailService $customerDetailService)
    {
    }

    public function index(ListCustomerDetailsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerDetail::class);

        return $this->successCollection(
            $this->customerDetailService->paginate(ListQueryData::fromRequest($request), $request->user()),
            CustomerDetailResource::class,
            __('Customer details retrieved successfully.'),
        );
    }

    public function store(StoreCustomerDetailRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerDetail::class);

        $customerDetail = $this->customerDetailService->create(CustomerDetailData::fromArray($request->validated()));

        return $this->successItem($customerDetail, CustomerDetailResource::class, __('Customer detail created successfully.'), 201);
    }

    public function show(CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorize('view', $customerDetail);

        return $this->successItem(
            $this->customerDetailService->find($customerDetail->id, request()->user()),
            CustomerDetailResource::class,
            __('Customer detail retrieved successfully.'),
        );
    }

    public function update(UpdateCustomerDetailRequest $request, CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorize('update', $customerDetail);

        $updatedCustomerDetail = $this->customerDetailService->update($customerDetail, CustomerDetailData::fromArray([
            ...$customerDetail->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedCustomerDetail, CustomerDetailResource::class, __('Customer detail updated successfully.'));
    }

    public function destroy(CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorize('delete', $customerDetail);

        $this->customerDetailService->delete($customerDetail->id, request()->user());

        return $this->success(null, __('Customer detail deleted successfully.'));
    }
}
