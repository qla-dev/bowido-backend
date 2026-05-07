<?php

namespace App\Http\Controllers;

use App\Models\CustomerDetail;
use App\Models\User;
use App\Services\BillingCounterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CustomerDetailController extends ApiController
{
    public function __construct(private readonly BillingCounterService $billingCounterService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'list');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        [$limit, $offset, $filters] = $this->listParams($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'kvk' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = CustomerDetail::query()
            ->with('user.role')
            ->when($filters['user_id'] ?? null, fn ($builder, $value) => $builder->where('user_id', (int) $value))
            ->when($filters['company_name'] ?? null, fn ($builder, $value) => $builder->where('company_name', 'like', '%'.$value.'%'))
            ->when($filters['kvk'] ?? null, fn ($builder, $value) => $builder->where('kvk', 'like', '%'.$value.'%'))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->orderBy('company_name');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'customer_detail', 'Customer details retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate($this->storeRules());

        $customerDetail = CustomerDetail::query()->create($this->payload($validated))->load('user.role');

        return $this->successItem($customerDetail, 'customer_detail', 'Customer detail created successfully.', 201);
    }

    public function show(Request $request, CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'view');
        $this->authorizeCustomerOwner($request, $customerDetail->user_id, 'You are not allowed to view another customer\'s details.');

        return $this->successItem($customerDetail->load('user.role'), 'customer_detail', 'Customer detail retrieved successfully.');
    }

    public function update(Request $request, CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'update');
        $this->authorizeCustomerOwner($request, $customerDetail->user_id, 'You are not allowed to update another customer\'s details.');

        $validated = $request->validate($this->updateRules($customerDetail));
        $customerDetail->fill($this->payload($validated, $customerDetail));
        $customerDetail->save();

        return $this->successItem($customerDetail->fresh('user.role'), 'customer_detail', 'Customer detail updated successfully.');
    }

    public function destroy(Request $request, CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        $customerDetail->delete();

        return $this->success(null, 'Customer detail deleted successfully.');
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'view');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);
        $needle = $validated['q'];
        $limit = (int) ($validated['limit'] ?? 10);

        $customers = User::query()
            ->with('customerDetail')
            ->whereHas('role', fn (Builder $builder) => $builder->where('name', 'customer'))
            ->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->where('name', 'like', '%'.$needle.'%')
                    ->orWhere('email', 'like', '%'.$needle.'%')
                    ->orWhereHas('customerDetail', function (Builder $detailQuery) use ($needle): void {
                        $detailQuery
                            ->where('company_name', 'like', '%'.$needle.'%')
                            ->orWhere('kvk', 'like', '%'.$needle.'%');
                    });
            })
            ->limit($limit)
            ->get()
            ->map(fn (User $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'company_name' => $customer->customerDetail?->company_name,
                'kvk' => $customer->customerDetail?->kvk,
            ])
            ->values();

        return $this->success($customers->all(), 'Customer search results retrieved successfully.');
    }

    public function byKvk(string $kvk, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'view');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $customerDetail = CustomerDetail::query()
            ->with('user.role')
            ->where('kvk', $kvk)
            ->firstOrFail();

        return $this->success([
            'customer_id' => $customerDetail->user_id,
            'customer_name' => $customerDetail->company_name,
            'kvk' => $customerDetail->kvk,
            'billing_email' => $customerDetail->billing_email,
            'billing_address' => $customerDetail->billing_address,
            'delivery_address' => $customerDetail->delivery_address,
        ], 'Customer KVK data retrieved successfully.');
    }

    public function currentCosts(User $customer, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'view');
        $this->authorizeCustomerOwner($request, $customer->id, 'You are not allowed to view another customer\'s costs.');

        return $this->success($this->billingCounterService->calculateForCustomer($customer), 'Customer current costs calculated successfully.');
    }

    public function weeklyDigestPreview(User $customer, Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'customer_details', 'view');
        $this->authorizeCustomerOwner($request, $customer->id, 'You are not allowed to preview another customer\'s digest.');

        return $this->success(
            $this->digestPayload($customer, Carbon::now()->startOfWeek()),
            'Weekly digest preview generated successfully.',
        );
    }

    public function runWeeklyDigest(Request $request): JsonResponse
    {
        abort_if(
            $request->user()->isCustomer() || ! $request->user()->hasModulePermission('invoices', 'viewAny'),
            Response::HTTP_FORBIDDEN,
            'This action is unauthorized.',
        );

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'send_email' => ['sometimes', 'boolean'],
            'week_start' => ['nullable', 'date'],
        ]);
        $weekStart = isset($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfDay()
            : Carbon::now()->startOfWeek();
        $sendEmail = (bool) ($validated['send_email'] ?? false);
        $query = User::query()
            ->with('customerDetail')
            ->whereHas('role', fn (Builder $builder) => $builder->where('name', 'customer'));

        if (($customerId = $validated['customer_id'] ?? null) !== null) {
            $query->whereKey((int) $customerId);
        }

        $digests = $query->get()
            ->map(fn (User $customer): array => $this->digestPayload($customer, $weekStart))
            ->values();

        return $this->success([
            'send_email' => $sendEmail,
            'emails_dispatched' => 0,
            'delivery_mode' => $sendEmail ? 'placeholder_only' : 'preview_only',
            'digests' => $digests->all(),
        ], 'Weekly digest run completed successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:customer_details,user_id'],
            'company_name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'kvk' => ['nullable', 'string', 'max:255', 'unique:customer_details,kvk'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'default_price_per_day' => ['required', 'numeric', 'min:0'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(CustomerDetail $customerDetail): array
    {
        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id', Rule::unique('customer_details', 'user_id')->ignore($customerDetail->id)],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'kvk' => ['nullable', 'string', 'max:255', Rule::unique('customer_details', 'kvk')->ignore($customerDetail->id)],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'default_price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated, ?CustomerDetail $customerDetail = null): array
    {
        return [
            'user_id' => $validated['user_id'] ?? $customerDetail?->user_id,
            'company_name' => isset($validated['company_name']) ? trim((string) $validated['company_name']) : $customerDetail?->company_name,
            'country' => array_key_exists('country', $validated) ? $this->normalizeText($validated['country']) : $customerDetail?->country,
            'kvk' => array_key_exists('kvk', $validated) ? $this->normalizeText($validated['kvk']) : $customerDetail?->kvk,
            'billing_email' => $validated['billing_email'] ?? $customerDetail?->billing_email,
            'billing_address' => array_key_exists('billing_address', $validated) ? $this->normalizeText($validated['billing_address']) : $customerDetail?->billing_address,
            'delivery_address' => array_key_exists('delivery_address', $validated) ? $this->normalizeText($validated['delivery_address']) : $customerDetail?->delivery_address,
            'tax_number' => array_key_exists('tax_number', $validated) ? $this->normalizeText($validated['tax_number']) : $customerDetail?->tax_number,
            'vat_number' => array_key_exists('vat_number', $validated) ? $this->normalizeText($validated['vat_number']) : $customerDetail?->vat_number,
            'default_price_per_day' => $validated['default_price_per_day'] ?? $customerDetail?->default_price_per_day ?? 0,
            'grace_period_days' => $validated['grace_period_days'] ?? $customerDetail?->grace_period_days ?? 0,
            'notes' => array_key_exists('notes', $validated) ? $this->normalizeText($validated['notes']) : $customerDetail?->notes,
            'is_active' => $validated['is_active'] ?? $customerDetail?->is_active ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function digestPayload(User $customer, Carbon $weekStart): array
    {
        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->customerDetail?->company_name ?? $customer->name,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'summary' => $this->billingCounterService->calculateForCustomer($customer),
        ];
    }
}