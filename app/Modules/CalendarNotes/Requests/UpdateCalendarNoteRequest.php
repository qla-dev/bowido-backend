<?php

namespace App\Modules\CalendarNotes\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateCalendarNoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'note_date' => ['sometimes', 'date'],
            'note_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'required', 'string'],
            'notified_user_ids' => ['sometimes', 'array'],
            'notified_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
