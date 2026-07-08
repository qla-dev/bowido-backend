<?php

namespace App\Modules\CalendarNotes\Services;

use App\Modules\CalendarNotes\DTOs\CalendarNoteData;
use App\Modules\CalendarNotes\Models\CalendarNote;
use App\Modules\CalendarNotes\Repositories\CalendarNoteRepository;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Users\Models\User;

class CalendarNoteService extends BaseCrudService
{
    public function __construct(private readonly CalendarNoteRepository $calendarNoteRepository)
    {
        parent::__construct($calendarNoteRepository);
    }

    public function create(CalendarNoteData $data, User $actor): CalendarNote
    {
        /** @var CalendarNote $note */
        $note = $this->calendarNoteRepository->create($data->toArray($actor->id));
        $this->syncNotifiedUsers($note, $data->notifiedUserIds);

        return $note->load(['creator.role', 'notifiedUsers.role']);
    }

    public function update(CalendarNote $note, CalendarNoteData $data, User $actor): CalendarNote
    {
        /** @var CalendarNote $updatedNote */
        $updatedNote = $this->calendarNoteRepository->update($note, $data->toArray($note->created_by_user_id ?: $actor->id));
        $this->syncNotifiedUsers($updatedNote, $data->notifiedUserIds);

        return $updatedNote->load(['creator.role', 'notifiedUsers.role']);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function syncNotifiedUsers(CalendarNote $note, array $userIds): void
    {
        $syncPayload = [];

        foreach ($userIds as $userId) {
            $syncPayload[$userId] = ['notified_at' => now()];
        }

        $note->notifiedUsers()->sync($syncPayload);
    }
}
