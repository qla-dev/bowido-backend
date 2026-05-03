<?php

namespace App\Modules\AuditLogs\Models;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'pallet_id',
        'made_by_user_id',
        'event_type',
        'note',
        'old_status_id',
        'new_status_id',
        'old_client_id',
        'new_client_id',
        'old_location',
        'new_location',
        'old_qr_code',
        'new_qr_code',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function madeByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by_user_id');
    }

    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function oldClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_client_id');
    }

    public function newClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_client_id');
    }
}
