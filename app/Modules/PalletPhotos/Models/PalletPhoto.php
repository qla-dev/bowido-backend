<?php

namespace App\Modules\PalletPhotos\Models;

use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\ServiceReports\Models\ServiceReport;
use App\Modules\Statuses\Models\Status;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletPhoto extends Model
{
    protected $fillable = [
        'pallet_id',
        'old_status_id',
        'new_status_id',
        'client_id',
        'service_report_id',
        'uploaded_by_user_id',
        'type',
        'warehouse_scope',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PalletPhotoType::class,
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function serviceReport(): BelongsTo
    {
        return $this->belongsTo(ServiceReport::class);
    }

    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
