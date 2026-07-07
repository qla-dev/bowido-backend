<?php

namespace App\Modules\Modules\Controllers;

use App\Modules\Modules\DTOs\ModuleData;
use App\Modules\Modules\Models\Module;
use App\Modules\Modules\Requests\ListModulesRequest;
use App\Modules\Modules\Requests\StoreModuleRequest;
use App\Modules\Modules\Requests\UpdateModuleRequest;
use App\Modules\Modules\Resources\ModuleResource;
use App\Modules\Modules\Services\ModuleService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class ModuleController extends ApiController
{
    public function __construct(private readonly ModuleService $moduleService)
    {
    }

    public function index(ListModulesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Module::class);

        $queryData = ListQueryData::fromRequest($request);

        if (! array_key_exists('is_active', $queryData->filters)) {
            $queryData = new ListQueryData(
                limit: $queryData->limit,
                offset: $queryData->offset,
                filters: [
                    ...$queryData->filters,
                    'is_active' => true,
                ],
                search: $queryData->search,
                sortBy: $queryData->sortBy,
                sortDirection: $queryData->sortDirection,
            );
        }

        return $this->successCollection(
            $this->moduleService->paginate($queryData, $request->user()),
            ModuleResource::class,
            __('Modules retrieved successfully.'),
        );
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $module = $this->moduleService->create(ModuleData::fromArray($request->validated()));

        return $this->successItem($module, ModuleResource::class, __('Module created successfully.'), 201);
    }

    public function show(Module $module): JsonResponse
    {
        $this->authorize('view', $module);

        return $this->successItem(
            $this->moduleService->find($module->id, request()->user()),
            ModuleResource::class,
            __('Module retrieved successfully.'),
        );
    }

    public function update(UpdateModuleRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $updatedModule = $this->moduleService->update($module, ModuleData::fromArray([
            ...$module->toArray(),
            ...$request->validated(),
        ]));

        return $this->successItem($updatedModule, ModuleResource::class, __('Module updated successfully.'));
    }

    public function destroy(Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $this->moduleService->delete($module->id, request()->user());

        return $this->success(null, __('Module deleted successfully.'));
    }
}
