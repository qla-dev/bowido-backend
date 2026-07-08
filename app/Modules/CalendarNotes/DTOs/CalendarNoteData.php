<?php

namespace App\Modules\CalendarNotes\DTOs;

use Carbon\Carbon;

readonly class CalendarNoteData
{
    /**
     * @param  array<int, int>  $notifiedUserIds
     */
    public function __construct(
        public string $noteDate,
        public ?string $noteTime,
        public ?string $title,
        public string $note,
        public array $notifiedUserIds,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $noteTime = self::normalizeTime($attributes['note_time'] ?? null);

        return new self(
            noteDate: Carbon::parse($attributes['note_date'])->toDateString(),
            noteTime: $noteTime,
            title: isset($attributes['title']) && trim((string) $attributes['title']) !== ''
                ? trim((string) $attributes['title'])
                : null,
            note: trim((string) $attributes['note']),
            notifiedUserIds: array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                $attributes['notified_user_ids'] ?? [],
            ))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $createdByUserId): array
    {
        return [
            'created_by_user_id' => $createdByUserId,
            'note_date' => $this->noteDate,
            'note_time' => $this->noteTime,
            'title' => $this->title,
            'note' => $this->note,
        ];
    }

    private static function normalizeTime(mixed $value): ?string
    {
        $time = trim((string) $value);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}/', $time, $matches) === 1) {
            return $matches[0];
        }

        return $time;
    }
}
