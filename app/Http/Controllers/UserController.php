<?php

namespace App\Http\Controllers;

use App\Models\CustomerDetail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'users', 'list');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        [$limit, $offset, $filters] = $this->listParams($request, [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $query = User::query()
            ->with(['role', 'customerDetail'])
            ->when($filters['role_id'] ?? null, fn ($builder, $roleId) => $builder->where('role_id', (int) $roleId))
            ->when($filters['email'] ?? null, fn ($builder, $email) => $builder->where('email', 'like', '%'.$email.'%'))
            ->when($filters['phone_number'] ?? null, fn ($builder, $phone) => $builder->where('phone_number', 'like', '%'.$phone.'%'))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->when($filters['name'] ?? null, fn ($builder, $name) => $builder->where('name', 'like', '%'.$name.'%'))
            ->orderBy('name');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'user', 'Users retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'users', 'create');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate($this->storeRules());

        $user = DB::transaction(function () use ($validated): User {
            /** @var User $user */
            $user = User::query()->create([
                'role_id' => (int) $validated['role_id'],
                'name' => trim((string) $validated['name']),
                'email' => strtolower((string) $validated['email']),
                'phone_number' => User::normalizePhoneNumber($validated['phone_number'] ?? null),
                'password' => $validated['password'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (isset($validated['customer_details'])) {
                CustomerDetail::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $this->customerDetailAttributes($validated['customer_details']),
                );
            }

            return $user->load(['role', 'customerDetail']);
        });

        return $this->successItem($user, 'user', 'User created successfully.', 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeModule($request, 'users', 'view');
        $this->authorizeCustomerOwner($request, $user->id, 'You are not allowed to view another user.');

        return $this->successItem($user->load(['role', 'customerDetail']), 'user', 'User retrieved successfully.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeModule($request, 'users', 'update');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        $validated = $request->validate($this->updateRules($user));

        $updatedUser = DB::transaction(function () use ($user, $validated): User {
            $payload = [
                'role_id' => $validated['role_id'] ?? $user->role_id,
                'name' => isset($validated['name']) ? trim((string) $validated['name']) : $user->name,
                'email' => isset($validated['email']) ? strtolower((string) $validated['email']) : $user->email,
                'phone_number' => array_key_exists('phone_number', $validated)
                    ? User::normalizePhoneNumber($validated['phone_number'])
                    : $user->phone_number,
                'is_active' => $validated['is_active'] ?? $user->is_active,
            ];

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $user->fill($payload);
            $user->save();

            if (isset($validated['customer_details'])) {
                CustomerDetail::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $this->customerDetailAttributes($validated['customer_details']),
                );
            }

            return $user->fresh(['role', 'customerDetail']);
        });

        return $this->successItem($updatedUser, 'user', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeModule($request, 'users', 'delete');
        abort_if($request->user()->isCustomer(), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        $user->delete();

        return $this->success(null, 'User deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:255', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'customer_details' => ['sometimes', 'array'],
            'customer_details.company_name' => ['required_with:customer_details', 'string', 'max:255'],
            'customer_details.country' => ['nullable', 'string', 'max:255'],
            'customer_details.kvk' => ['nullable', 'string', 'max:255', 'unique:customer_details,kvk'],
            'customer_details.billing_email' => ['nullable', 'email', 'max:255'],
            'customer_details.billing_address' => ['nullable', 'string'],
            'customer_details.delivery_address' => ['nullable', 'string'],
            'customer_details.tax_number' => ['nullable', 'string', 'max:255'],
            'customer_details.vat_number' => ['nullable', 'string', 'max:255'],
            'customer_details.default_price_per_day' => ['required_with:customer_details', 'numeric', 'min:0'],
            'customer_details.grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'customer_details.notes' => ['nullable', 'string'],
            'customer_details.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(User $user): array
    {
        return [
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'customer_details' => ['sometimes', 'array'],
            'customer_details.company_name' => ['required_with:customer_details', 'string', 'max:255'],
            'customer_details.country' => ['nullable', 'string', 'max:255'],
            'customer_details.kvk' => ['nullable', 'string', 'max:255', Rule::unique('customer_details', 'kvk')->ignore($user->customerDetail?->id)],
            'customer_details.billing_email' => ['nullable', 'email', 'max:255'],
            'customer_details.billing_address' => ['nullable', 'string'],
            'customer_details.delivery_address' => ['nullable', 'string'],
            'customer_details.tax_number' => ['nullable', 'string', 'max:255'],
            'customer_details.vat_number' => ['nullable', 'string', 'max:255'],
            'customer_details.default_price_per_day' => ['required_with:customer_details', 'numeric', 'min:0'],
            'customer_details.grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'customer_details.notes' => ['nullable', 'string'],
            'customer_details.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function customerDetailAttributes(array $attributes): array
    {
        return [
            'company_name' => trim((string) $attributes['company_name']),
            'country' => $this->normalizeText($attributes['country'] ?? null),
            'kvk' => $this->normalizeText($attributes['kvk'] ?? null),
            'billing_email' => $attributes['billing_email'] ?? null,
            'billing_address' => $this->normalizeText($attributes['billing_address'] ?? null),
            'delivery_address' => $this->normalizeText($attributes['delivery_address'] ?? null),
            'tax_number' => $this->normalizeText($attributes['tax_number'] ?? null),
            'vat_number' => $this->normalizeText($attributes['vat_number'] ?? null),
            'default_price_per_day' => $attributes['default_price_per_day'],
            'grace_period_days' => $attributes['grace_period_days'] ?? 0,
            'notes' => $this->normalizeText($attributes['notes'] ?? null),
            'is_active' => $attributes['is_active'] ?? true,
        ];
    }
}