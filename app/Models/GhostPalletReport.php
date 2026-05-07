<?php

namespace App\Models;

use Database\Factories\GhostPalletReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhostPalletReport extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PAIRED = 'paired';

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
        'image_path',
        'reported_at',
        'paired_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reported_at' => 'datetime',
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
}