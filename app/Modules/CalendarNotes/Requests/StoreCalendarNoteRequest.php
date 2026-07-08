<?php

namespace App\Modules\CalendarNotes\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class StoreCalendarNoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note_date' => ['required', 'date'],
            'note_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['required', 'string'],
            'notified_user_ids' => ['sometimes', 'array'],
            'notified_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
