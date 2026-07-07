<?php

namespace App\Modules\InvoiceItems\Repositories;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'invoice_id' => 'invoice_id',
            'pallet_id' => 'pallet_id',
            'period_start' => 'period_start',
            'period_end' => 'period_end',
        ];
    }

    protected function relations(): array
    {
        return ['pallet.currentStatus'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereHas('invoice', fn (Builder $builder) => $builder->where('user_id', $actor->id));
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
                ->where('description', 'like', $like)
                ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($like): void {
                    $invoiceQuery->where('invoice_number', 'like', $like);
                })
                ->orWhereHas('pallet', function (Builder $palletQuery) use ($like): void {
                    $palletQuery
                        ->where('pallet_name', 'like', $like)
                        ->orWhere('reference_code', 'like', $like)
                        ->orWhere('qr_code', 'like', $like);
                });
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'invoice' => fn (Builder $query, string $direction) => $query->orderBy(
                Invoice::query()
                    ->select('invoice_number')
                    ->whereColumn('invoices.id', 'invoice_items.invoice_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'pallet' => fn (Builder $query, string $direction) => $query->orderBy(
                Pallet::query()
                    ->selectRaw("COALESCE(NULLIF(pallet_name, ''), NULLIF(reference_code, ''), qr_code)")
                    ->whereColumn('pallets.id', 'invoice_items.pallet_id')
                    ->limit(1),
                $direction,
            )->orderBy('id'),
            'description' => 'description',
            'period_start' => 'period_start',
            'period_end' => 'period_end',
            'billed_days' => 'billed_days',
            'price_per_day' => 'price_per_day',
            'amount' => 'amount',
            'created_at' => 'created_at',
        ];
    }

    protected function model(): Model
    {
        return new InvoiceItem();
    }
}
