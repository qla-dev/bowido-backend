<?php

namespace App\Modules\CustomerDetails\Models;

use App\Modules\Users\Models\User;
use Database\Factories\CustomerDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDetail extends Model
{
    /** @use HasFactory<CustomerDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'country',
        'province',
        'kvk',
        'billing_email',
        'fixed_phone',
        'billing_address',
        'delivery_address',
        'tax_number',
        'vat_number',
        'default_price_per_day',
        'grace_period_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price_per_day' => 'decimal:2',
            'grace_period_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CustomerDetailFactory
    {
        return CustomerDetailFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
