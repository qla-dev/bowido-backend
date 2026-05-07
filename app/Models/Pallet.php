<?php

namespace App\Models;

use Database\Factories\PalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pallet extends Model
{
    /** @use HasFactory<PalletFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_status_id',
        'type',
        'asset_type',
        'qr_code',
        'reference_code',
        'current_location',
        'notes',
        'last_status_changed_at',
        'is_active',
        'is_ghost',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_status_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_ghost' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): PalletFactory
    {
        return PalletFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'current_status_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function serviceReports(): HasMany
    {
        return $this->hasMany(ServiceReport::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public static function normalizeQrCode(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->value();
    }
}