<?php

namespace App\Models;

use Database\Factories\ServiceReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReport extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    /** @use HasFactory<ServiceReportFactory> */
    use HasFactory;

    protected $fillable = [
        'pallet_id',
        'reported_by_user_id',
        'resolved_by_user_id',
        'status',
        'severity',
        'issue_type',
        'problem_description',
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
}