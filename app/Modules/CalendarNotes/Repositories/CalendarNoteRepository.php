<?php

namespace App\Modules\CalendarNotes\Repositories;

use App\Modules\CalendarNotes\Models\CalendarNote;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CalendarNoteRepository extends BaseRepository
{
    protected function allowedFilters(): array
    {
        return [
            'date_from' => fn (Builder $query, string $date): Builder => $query->whereDate('note_date', '>=', $date),
            'date_to' => fn (Builder $query, string $date): Builder => $query->whereDate('note_date', '<=', $date),
            'created_by_user_id' => 'created_by_user_id',
            'notified_user_id' => fn (Builder $query, int $userId): Builder => $query->whereHas(
                'notifiedUsers',
                fn (Builder $userQuery): Builder => $userQuery->whereKey($userId),
            ),
        ];
    }

    protected function relations(): array
    {
        return ['creator.role', 'notifiedUsers.role'];
    }

    protected function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = "%{$search}%";

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->where('title', 'like', $like)
                ->orWhere('note', 'like', $like)
                ->orWhereHas('creator', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', $like))
                ->orWhereHas('notifiedUsers', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', $like));
        });
    }

    protected function allowedSorts(): array
    {
        return [
            'note_date' => 'note_date',
            'note_time' => 'note_time',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'title' => 'title',
        ];
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('note_date')
            ->orderBy('note_time')
            ->orderBy('id');
    }

    protected function model(): Model
    {
        return new CalendarNote;
    }
}
