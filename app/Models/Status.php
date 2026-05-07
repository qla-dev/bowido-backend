<?php

namespace App\Models;

use App\Models\AuditLog;
use App\Models\Pallet;
use Database\Factories\StatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    /** @use HasFactory<StatusFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_billable',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): StatusFactory
    {
        return StatusFactory::new();
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class, 'current_status_id');
    }

    public function auditLogsAsOldStatus(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'old_status_id');
    }

    public function auditLogsAsNewStatus(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'new_status_id');
    }
}
