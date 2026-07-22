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
        'street',
        'house_number',
        'postal_code',
        'city',
        'kvk',
        'billing_email',
        'fixed_phone',
        'warehouse_scope',
        'warehouse1_street',
        'warehouse1_house_number',
        'warehouse1_postal_code',
        'warehouse1_city',
        'warehouse2_street',
        'warehouse2_house_number',
        'warehouse2_postal_code',
        'warehouse2_city',
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

    public function warehouseOneAddress(): ?string
    {
        return $this->formatAddress(
            $this->warehouse1_street,
            $this->warehouse1_house_number,
            $this->warehouse1_postal_code,
            $this->warehouse1_city,
        );
    }

    public function businessAddress(): ?string
    {
        return $this->formatAddress(
            $this->street,
            $this->house_number,
            $this->postal_code,
            $this->city,
        );
    }

    private function formatAddress(
        ?string $street,
        ?string $houseNumber,
        ?string $postalCode,
        ?string $city,
    ): ?string {
        $streetLine = trim(implode(' ', array_filter([$street, $houseNumber])));
        $localityLine = trim(implode(' ', array_filter([$postalCode, $city])));
        $address = implode(', ', array_filter([$streetLine, $localityLine]));

        return $address !== '' ? $address : null;
    }
}
