<?php

namespace App\Modules\CalendarNotes\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CalendarNote extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'note_date',
        'note_time',
        'title',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function notifiedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_note_user')
            ->withPivot('notified_at')
            ->withTimestamps();
    }
}
