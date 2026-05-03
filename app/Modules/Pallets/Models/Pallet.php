<?php

namespace App\Modules\Pallets\Models;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\InvoiceItems\Models\InvoiceItem;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Database\Factories\PalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pallet extends Model
{
    /** @use HasFactory<PalletFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_status_id',
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
}
