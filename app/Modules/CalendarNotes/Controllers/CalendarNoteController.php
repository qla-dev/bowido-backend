<?php

namespace App\Modules\CalendarNotes\Controllers;

use App\Modules\CalendarNotes\DTOs\CalendarNoteData;
use App\Modules\CalendarNotes\Models\CalendarNote;
use App\Modules\CalendarNotes\Requests\ListCalendarNotesRequest;
use App\Modules\CalendarNotes\Requests\StoreCalendarNoteRequest;
use App\Modules\CalendarNotes\Requests\UpdateCalendarNoteRequest;
use App\Modules\CalendarNotes\Resources\CalendarNoteResource;
use App\Modules\CalendarNotes\Services\CalendarNoteService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Users\Models\User;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarNoteController extends ApiController
{
    public function __construct(private readonly CalendarNoteService $calendarNoteService) {}

    public function index(ListCalendarNotesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarNote::class);

        return $this->successCollection(
            $this->calendarNoteService->paginate(ListQueryData::fromRequest($request), $request->user()),
            CalendarNoteResource::class,
            __('Calendar notes retrieved successfully.'),
        );
    }

    public function notifyCandidates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarNote::class);

        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $like = "%{$search}%";

        $users = User::query()
            ->with(['role', 'customerDetail'])
            ->where('is_active', true)
            ->whereHas('role', function (Builder $roleQuery): void {
                $roleQuery->whereRaw('lower(name) <> ?', ['customer']);
            })
            ->when($search !== '', function (Builder $query) use ($like): void {
                $query->where(function (Builder $builder) use ($like): void {
                    $builder
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('name', 'like', $like));
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $this->success(
            data: UserResource::collection($users)->resolve(),
            message: __('Notification candidates retrieved successfully.'),
        );
    }

    public function store(StoreCalendarNoteRequest $request): JsonResponse
    {
        $this->authorize('create', CalendarNote::class);

        $note = $this->calendarNoteService->create(
            CalendarNoteData::fromArray($request->validated()),
            $request->user(),
        );

        return $this->successItem($note, CalendarNoteResource::class, __('Calendar note created successfully.'), 201);
    }

    public function show(CalendarNote $calendarNote): JsonResponse
    {
        $this->authorize('view', $calendarNote);

        return $this->successItem(
            $this->calendarNoteService->find($calendarNote->id, request()->user()),
            CalendarNoteResource::class,
            __('Calendar note retrieved successfully.'),
        );
    }

    public function update(UpdateCalendarNoteRequest $request, CalendarNote $calendarNote): JsonResponse
    {
        $this->authorize('update', $calendarNote);

        $updatedNote = $this->calendarNoteService->update($calendarNote, CalendarNoteData::fromArray([
            ...$calendarNote->toArray(),
            'notified_user_ids' => $calendarNote->notifiedUsers()->pluck('users.id')->all(),
            ...$request->validated(),
        ]), $request->user());

        return $this->successItem($updatedNote, CalendarNoteResource::class, __('Calendar note updated successfully.'));
    }

    public function destroy(CalendarNote $calendarNote): JsonResponse
    {
        $this->authorize('delete', $calendarNote);

        $this->calendarNoteService->delete($calendarNote->id, request()->user());

        return $this->success(null, __('Calendar note deleted successfully.'));
    }
}
