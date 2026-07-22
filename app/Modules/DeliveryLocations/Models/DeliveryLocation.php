<?php

namespace App\Modules\DeliveryLocations\Models;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLocation extends Model
{
    protected $fillable = [
        'pallet_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'formatted_address',
        'street',
        'house_number',
        'city',
        'postal_code',
        'country',
        'country_code',
        'provider',
        'source',
        'confirmed_by_user',
        'created_by_user_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'confirmed_by_user' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
