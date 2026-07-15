<?php

namespace App\Modules\ServiceReports\Models;

use App\Modules\Pallets\Models\Pallet;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\Users\Models\User;
use Database\Factories\ServiceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceReport extends Model
{
    /** @use HasFactory<ServiceReportFactory> */
    use HasFactory;

    protected $fillable = [
        'pallet_id',
        'reported_by_user_id',
        'resolved_by_user_id',
        'status',
        'severity',
        'issue_type',
        'description',
        'resolution_note',
        'image_path',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): ServiceReportFactory
    {
        return ServiceReportFactory::new();
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PalletPhoto::class);
    }
}
