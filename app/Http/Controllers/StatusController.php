<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StatusController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'statuses', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'slug' => ['sometimes', 'string', 'max:255'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Status::query()
            ->when($filters['slug'] ?? null, fn ($builder, $slug) => $builder->where('slug', 'like', '%'.$slug.'%'))
            ->when(array_key_exists('is_billable', $filters), fn ($builder) => $builder->where('is_billable', (bool) $filters['is_billable']))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->orderBy('sort_order')
            ->orderBy('name');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'status', 'Statuses retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'statuses', 'create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:statuses,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:statuses,slug'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $status = Status::query()->create([
            'name' => Str::of($validated['name'])->squish()->title()->value(),
            'slug' => isset($validated['slug'])
                ? Str::of($validated['slug'])->squish()->lower()->slug('_')->value()
                : Str::of($validated['name'])->squish()->lower()->slug('_')->value(),
            'description' => $this->normalizeText($validated['description'] ?? null),
            'is_billable' => $validated['is_billable'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->successItem($status, 'status', 'Status created successfully.', 201);
    }

    public function show(Request $request, Status $status): JsonResponse
    {
        $this->authorizeModule($request, 'statuses', 'view');

        return $this->successItem($status, 'status', 'Status retrieved successfully.');
    }

    public function update(Request $request, Status $status): JsonResponse
    {
        $this->authorizeModule($request, 'statuses', 'update');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('statuses', 'name')->ignore($status->id)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('statuses', 'slug')->ignore($status->id)],
            'description' => ['nullable', 'string'],
            'is_billable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $status->fill([
            'name' => isset($validated['name']) ? Str::of($validated['name'])->squish()->title()->value() : $status->name,
            'slug' => isset($validated['slug'])
                ? Str::of($validated['slug'])->squish()->lower()->slug('_')->value()
                : $status->slug,
            'description' => array_key_exists('description', $validated) ? $this->normalizeText($validated['description']) : $status->description,
            'is_billable' => $validated['is_billable'] ?? $status->is_billable,
            'is_active' => $validated['is_active'] ?? $status->is_active,
            'sort_order' => $validated['sort_order'] ?? $status->sort_order,
        ]);
        $status->save();

        return $this->successItem($status->fresh(), 'status', 'Status updated successfully.');
    }

    public function destroy(Request $request, Status $status): JsonResponse
    {
        $this->authorizeModule($request, 'statuses', 'delete');
        $status->delete();

        return $this->success(null, 'Status deleted successfully.');
    }
}