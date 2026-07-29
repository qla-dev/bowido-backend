<?php

namespace App\Modules\GhostPalletReports\Models;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Users\Models\User;
use Database\Factories\GhostPalletReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhostPalletReport extends Model
{
    /** @use HasFactory<GhostPalletReportFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'paired_pallet_id',
        'status',
        'quantity',
        'location',
        'description',
        'notes',
        'paired_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'paired_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): GhostPalletReportFactory
    {
        return GhostPalletReportFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pairedPallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class, 'paired_pallet_id');
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class, 'ghost_pallet_report_id');
    }
}
