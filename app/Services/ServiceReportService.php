<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\ServiceReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ServiceReportService
{
    public function __construct(private readonly PalletStatusService $palletStatusService)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): ServiceReport
    {
        $pallet = Pallet::query()->findOrFail((int) $attributes['pallet_id']);

        if ($actor->isCustomer() && $pallet->user_id !== $actor->id) {
            throw new AuthorizationException('You are not allowed to create a report for this pallet.');
        }

        return DB::transaction(function () use ($attributes, $actor): ServiceReport {
            /** @var ServiceReport $serviceReport */
            $serviceReport = ServiceReport::query()->create([
                'pallet_id' => (int) $attributes['pallet_id'],
                'reported_by_user_id' => $actor->id,
                'status' => ServiceReport::STATUS_OPEN,
                'severity' => $attributes['severity'] ?? null,
                'issue_type' => $attributes['issue_type'] ?? null,
                'problem_description' => $attributes['problem_description'] ?? $attributes['description'],
                'description' => $attributes['description'],
                'image_path' => ($attributes['image'] ?? null) instanceof UploadedFile
                    ? $attributes['image']->store('service-reports', 'public')
                    : null,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            return $serviceReport->load(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ServiceReport $serviceReport, array $attributes, User $actor): ServiceReport
    {
        return DB::transaction(function () use ($serviceReport, $attributes, $actor): ServiceReport {
            $lockedServiceReport = ServiceReport::query()->lockForUpdate()->findOrFail($serviceReport->id);

            if (
                $lockedServiceReport->status === ServiceReport::STATUS_RESOLVED
                && ($attributes['status'] ?? null) === ServiceReport::STATUS_OPEN
            ) {
                throw ValidationException::withMessages([
                    'status' => ['Resolved reports cannot be reopened through this endpoint.'],
                ]);
            }

            $updateAttributes = array_filter([
                'pallet_id' => $attributes['pallet_id'] ?? null,
                'severity' => $attributes['severity'] ?? null,
                'issue_type' => $attributes['issue_type'] ?? null,
                'problem_description' => $attributes['problem_description'] ?? $attributes['description'] ?? null,
                'description' => $attributes['description'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
            ], static fn ($value): bool => $value !== null && $value !== '');

            if (($attributes['image'] ?? null) instanceof UploadedFile) {
                $updateAttributes['image_path'] = $attributes['image']->store('service-reports', 'public');
            }

            if (($attributes['status'] ?? null) === ServiceReport::STATUS_RESOLVED && $lockedServiceReport->status !== ServiceReport::STATUS_RESOLVED) {
                $updateAttributes['status'] = ServiceReport::STATUS_RESOLVED;
                $updateAttributes['resolved_by_user_id'] = $actor->id;
                $updateAttributes['resolved_at'] = now();
                $updateAttributes['resolution_note'] = $attributes['resolution_note'] ?? null;
            } elseif (($attributes['status'] ?? null) !== null) {
                $updateAttributes['status'] = $attributes['status'];
            }

            $lockedServiceReport->fill($updateAttributes);
            $lockedServiceReport->save();

            return $lockedServiceReport->fresh(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reportPalletDamage(Pallet $pallet, array $attributes, User $actor): ServiceReport
    {
        $pallet->loadMissing(['user.role', 'currentStatus']);

        if ($actor->isCustomer() && $pallet->user_id !== $actor->id) {
            throw new AuthorizationException('You are not allowed to report damage for this pallet.');
        }

        return DB::transaction(function () use ($pallet, $attributes, $actor): ServiceReport {
            $imagePaths = collect($attributes['images'] ?? [])
                ->filter(fn ($file): bool => $file instanceof UploadedFile)
                ->map(fn (UploadedFile $file): string => $file->store('service-reports', 'public'))
                ->values()
                ->all();

            /** @var ServiceReport $serviceReport */
            $serviceReport = ServiceReport::query()->create([
                'pallet_id' => $pallet->id,
                'reported_by_user_id' => $actor->id,
                'status' => ServiceReport::STATUS_OPEN,
                'severity' => $attributes['severity'] ?? null,
                'issue_type' => $attributes['issue_type'] ?? 'damage',
                'problem_description' => $attributes['problem_description'],
                'description' => $attributes['problem_description'],
                'image_path' => $imagePaths[0] ?? null,
                'metadata' => [
                    'images' => $imagePaths,
                    'location' => $attributes['location'] ?? null,
                ],
            ]);

            $this->palletStatusService->moveToService(
                pallet: $pallet,
                location: $attributes['location'] ?? null,
                note: $attributes['problem_description'],
                actor: $actor,
            );

            return $serviceReport->fresh(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }

    public function resolvePalletReport(
        Pallet $pallet,
        int $newStatusId,
        ?string $resolutionNote,
        ?string $location,
        User $actor,
    ): ServiceReport {
        return DB::transaction(function () use ($pallet, $newStatusId, $resolutionNote, $location, $actor): ServiceReport {
            /** @var ServiceReport|null $serviceReport */
            $serviceReport = ServiceReport::query()
                ->where('pallet_id', $pallet->id)
                ->where('status', ServiceReport::STATUS_OPEN)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $serviceReport instanceof ServiceReport) {
                throw new BadRequestHttpException('There is no open service report for this pallet.');
            }

            $serviceReport->fill([
                'status' => ServiceReport::STATUS_RESOLVED,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'resolution_note' => $resolutionNote,
            ]);
            $serviceReport->save();

            $this->palletStatusService->transitionFromService(
                pallet: $pallet,
                statusId: $newStatusId,
                location: $location,
                note: $resolutionNote,
                actor: $actor,
            );

            return $serviceReport->fresh(['pallet.user.role', 'pallet.currentStatus', 'reportedByUser.role', 'resolvedByUser.role']);
        });
    }
}