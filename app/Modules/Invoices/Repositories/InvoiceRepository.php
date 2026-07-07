<?php

namespace App\Modules\Invoices\Repositories;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InvoiceRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'user_id' => 'user_id',
            'invoice_number' => 'invoice_number',
            'status' => 'status',
            'currency' => 'currency',
            'period_start' => 'period_start',
            'period_end' => 'period_end',
        ];
    }

    protected function relations(): array
    {
        return ['user.customerDetail'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->where('user_id', $actor->id);
        }

        return $query;
    }

    protected function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('invoice_number', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('currency', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('customerDetail', function (Builder $detailQuery) use ($like): void {
                            $detailQuery
                                ->where('company_name', 'like', $like)
                                ->orWhere('kvk', 'like', $like)
                                ->orWhere('billing_email', 'like', $like);
                        });
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'invoice_number' => 'invoice_number',
            'status' => 'status',
            'currency' => 'currency',
            'customer' => fn (Builder $query, string $direction) => $query->orderBy(
                CustomerDetail::query()
                    ->select('company_name')
                    ->whereColumn('customer_details.user_id', 'invoices.user_id')
                    ->limit(1),
                $direction,
            )->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'invoices.user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'customer_name' => fn (Builder $query, string $direction) => $query->orderBy(
                CustomerDetail::query()
                    ->select('company_name')
                    ->whereColumn('customer_details.user_id', 'invoices.user_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'issue_date' => 'issued_at',
            'issued_at' => 'issued_at',
            'due_date' => 'due_at',
            'due_at' => 'due_at',
            'period_start' => 'period_start',
            'period_end' => 'period_end',
            'total_amount' => 'total_amount',
            'amount' => 'total_amount',
            'created_at' => 'created_at',
        ];
    }

    public function lockForUpdate(int $id): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $invoice;
    }

    protected function model(): Model
    {
        return new Invoice();
    }
}
