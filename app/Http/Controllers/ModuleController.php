<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModuleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'modules', 'list');
        [$limit, $offset, $filters] = $this->listParams($request, [
            'slug' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Module::query()
            ->with('rolePermissions.role')
            ->when($filters['slug'] ?? null, fn ($builder, $slug) => $builder->where('slug', 'like', '%'.$slug.'%'))
            ->when(array_key_exists('is_active', $filters), fn ($builder) => $builder->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name');

        [$items, $meta] = $this->paginateQuery($query, $limit, $offset);

        return $this->successCollection($items, 'module', 'Modules retrieved successfully.', $meta);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule($request, 'modules', 'create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:modules,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:modules,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $module = Module::query()->create([
            'name' => Str::of($validated['name'])->squish()->title()->value(),
            'slug' => isset($validated['slug'])
                ? Str::of($validated['slug'])->squish()->lower()->slug('_')->value()
                : Str::of($validated['name'])->squish()->lower()->slug('_')->value(),
            'description' => $this->normalizeText($validated['description'] ?? null),
            'is_active' => $validated['is_active'] ?? true,
        ])->load('rolePermissions.role');

        return $this->successItem($module, 'module', 'Module created successfully.', 201);
    }

    public function show(Request $request, Module $module): JsonResponse
    {
        $this->authorizeModule($request, 'modules', 'view');

        return $this->successItem($module->load('rolePermissions.role'), 'module', 'Module retrieved successfully.');
    }

    public function update(Request $request, Module $module): JsonResponse
    {
        $this->authorizeModule($request, 'modules', 'update');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('modules', 'name')->ignore($module->id)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('modules', 'slug')->ignore($module->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $module->fill([
            'name' => isset($validated['name']) ? Str::of($validated['name'])->squish()->title()->value() : $module->name,
            'slug' => isset($validated['slug'])
                ? Str::of($validated['slug'])->squish()->lower()->slug('_')->value()
                : $module->slug,
            'description' => array_key_exists('description', $validated) ? $this->normalizeText($validated['description']) : $module->description,
            'is_active' => $validated['is_active'] ?? $module->is_active,
        ]);
        $module->save();

        return $this->successItem($module->fresh('rolePermissions.role'), 'module', 'Module updated successfully.');
    }

    public function destroy(Request $request, Module $module): JsonResponse
    {
        $this->authorizeModule($request, 'modules', 'delete');
        $module->delete();

        return $this->success(null, 'Module deleted successfully.');
    }
}