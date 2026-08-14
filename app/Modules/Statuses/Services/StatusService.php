<?php

namespace App\Modules\Statuses\Services;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\Shared\Services\BaseCrudService;
use App\Modules\Statuses\DTOs\StatusData;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Repositories\StatusRepository;
use Illuminate\Support\Facades\DB;

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
        $billingSettingsChanged = $status->grace_period_days !== $data->gracePeriodDays
            || round((float) $status->price_per_day, 2) !== $data->pricePerDay;

        /** @var Status $updatedStatus */
        $updatedStatus = DB::transaction(function () use ($status, $data, $billingSettingsChanged): Status {
            /** @var Status $updated */
            $updated = $this->statusRepository->update($status, $data->toArray());

            // Client billing details intentionally mirror the configuration
            // value. Updating them in the same transaction makes the new
            // grace period and rate effective for every client together.
            if ($billingSettingsChanged) {
                CustomerDetail::query()->update([
                    'grace_period_days' => $data->gracePeriodDays,
                    'default_price_per_day' => $data->pricePerDay,
                    'updated_at' => now(),
                ]);
            }

            return $updated;
        });

        return $updatedStatus;
    }
}
