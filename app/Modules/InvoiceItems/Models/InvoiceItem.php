<?php

namespace App\Modules\InvoiceItems\Models;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Pallets\Models\Pallet;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'pallet_id',
        'description',
        'period_start',
        'period_end',
        'billed_days',
        'price_per_day',
        'amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'billed_days' => 'integer',
            'price_per_day' => 'decimal:2',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }
}
