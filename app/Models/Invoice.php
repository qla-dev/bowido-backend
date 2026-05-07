<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_number',
        'status',
        'currency',
        'billing_period_start',
        'billing_period_end',
        'period_start',
        'period_end',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal_amount',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}