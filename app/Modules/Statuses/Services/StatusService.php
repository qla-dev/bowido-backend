<?php

namespace App\Modules\Statuses\Services;

use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Statuses\DTOs\StatusData;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Repositories\StatusRepository;

class StatusService extends BaseCrudService
{
    public function __construct(private readonly StatusRepository $statusRepository)
    {
        parent::__construct($statusRepository);
    }

    public function create(StatusData $data): Status
    {
        /** @var Status $status */
        $status = $this->statusRepository->create($data->toArray());

        return $status;
    }

    public function update(Status $status, StatusData $data): Status
    {
        /** @var Status $updatedStatus */
        $updatedStatus = $this->statusRepository->update($status, $data->toArray());

        return $updatedStatus;
    }
}
