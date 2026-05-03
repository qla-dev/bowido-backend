<?php

namespace App\Modules\Modules\Services;

use App\Modules\Modules\DTOs\ModuleData;
use App\Modules\Modules\Models\Module;
use App\Modules\Modules\Repositories\ModuleRepository;
use App\Modules\Shared\Services\BaseCrudService;

class ModuleService extends BaseCrudService
{
    public function __construct(private readonly ModuleRepository $moduleRepository)
    {
        parent::__construct($moduleRepository);
    }

    public function create(ModuleData $data): Module
    {
        /** @var Module $module */
        $module = $this->moduleRepository->create($data->toArray());

        return $module->load('rolePermissions.role');
    }

    public function update(Module $module, ModuleData $data): Module
    {
        /** @var Module $updatedModule */
        $updatedModule = $this->moduleRepository->update($module, $data->toArray());

        return $updatedModule->load('rolePermissions.role');
    }
}
