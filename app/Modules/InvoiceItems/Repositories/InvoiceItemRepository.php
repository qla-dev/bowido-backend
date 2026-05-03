<?php

namespace App\Modules\InvoiceItems\Repositories;

use App\Modules\InvoiceItems\Models\InvoiceItem;
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
        return ['invoice.user.role', 'pallet.currentStatus'];
    }

    protected function scopeForActor(Builder $query, ?User $actor): Builder
    {
        if ($actor?->isCustomer()) {
            $query->whereHas('invoice', fn (Builder $builder) => $builder->where('user_id', $actor->id));
        }

        return $query;
    }

    protected function model(): Model
    {
        return new InvoiceItem();
    }
}
