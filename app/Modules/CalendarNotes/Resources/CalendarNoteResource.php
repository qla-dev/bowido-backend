<?php

namespace App\Modules\CalendarNotes\Resources;

use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_by_user_id' => $this->created_by_user_id,
            'created_by_user_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'note_date' => $this->note_date?->toDateString(),
            'note_time' => $this->note_time ? substr((string) $this->note_time, 0, 5) : null,
            'title' => $this->title,
            'note' => $this->note,
            'notified_user_ids' => $this->whenLoaded(
                'notifiedUsers',
                fn (): array => $this->notifiedUsers->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ),
            'notified_users' => UserResource::collection($this->whenLoaded('notifiedUsers')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
