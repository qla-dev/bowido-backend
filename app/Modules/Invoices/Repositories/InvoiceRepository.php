<?php

namespace App\Modules\Invoices\Repositories;

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
        return ['user.role', 'user.customerDetail', 'items.pallet.currentStatus'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->where('user_id', $actor->id);
        }

        return $query;
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
