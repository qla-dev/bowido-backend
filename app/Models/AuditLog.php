<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const EVENT_CREATED = 'created';
    public const EVENT_STATUS_CHANGED = 'status_changed';
    public const EVENT_CLIENT_CHANGED = 'client_changed';
    public const EVENT_LOCATION_CHANGED = 'location_changed';
    public const EVENT_QR_CODE_CHANGED = 'qr_code_changed';
    public const EVENT_GHOST_PAIRED = 'ghost_pallet_paired';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';

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
        'qr_code_version',
        'old_qr_code',
        'new_qr_code',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'qr_code_version' => 'integer',
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