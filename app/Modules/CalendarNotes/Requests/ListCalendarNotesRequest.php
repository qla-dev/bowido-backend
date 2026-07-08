<?php

namespace App\Modules\CalendarNotes\Requests;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

class ListCalendarNotesRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'created_by_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'notified_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
