<?php

namespace App\Modules\Pallets\Resources;

use App\Modules\DeliveryLocations\Resources\DeliveryLocationResource;
use App\Modules\Statuses\Resources\StatusResource;
use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentClientName = $this->relationLoaded('user')
            ? $this->user?->customerDetail?->company_name ?? $this->user?->name
            : null;
        $deletedClientName = is_array($this->metadata)
            ? ($this->metadata['deleted_client_name'] ?? null)
            : null;
        $notes = trim((string) $this->notes);
        $reportNotes = $this->relationLoaded('ghostPalletReport')
            ? trim((string) $this->ghostPalletReport?->notes)
            : '';

        if ($reportNotes !== '' && ! str_contains($notes, $reportNotes)) {
            $notes = implode(' | ', array_filter([$reportNotes, $notes]));
        }

        $reportedLocation = null;
        if ($this->relationLoaded('ghostPalletReport') && $this->ghostPalletReport !== null) {
            $metadata = is_array($this->ghostPalletReport->metadata)
                ? $this->ghostPalletReport->metadata
                : [];
            $entries = is_array($metadata['entries'] ?? null) ? $metadata['entries'] : [];
            $entryIndex = null;

            foreach (array_map('trim', explode('|', $notes)) as $noteSegment) {
                if (preg_match('/^(?:Location|Locatie|Lokacija)\s+(\d+)$/iu', $noteSegment, $matches) === 1) {
                    $entryIndex = max(0, ((int) $matches[1]) - 1);
                    break;
                }
            }

            $entry = $entryIndex !== null && is_array($entries[$entryIndex] ?? null)
                ? $entries[$entryIndex]
                : (count($entries) === 1 && is_array($entries[0]) ? $entries[0] : null);
            $reportedLocation = is_array($entry) && filled($entry['location'] ?? null)
                ? trim((string) $entry['location'])
                : (filled($this->ghostPalletReport->location) ? trim((string) $this->ghostPalletReport->location) : null);
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'current_status_id' => $this->current_status_id,
            'current_status_name' => $this->whenLoaded('currentStatus', fn (): ?string => $this->currentStatus?->name),
            'current_status_slug' => $this->whenLoaded('currentStatus', fn (): ?string => $this->currentStatus?->slug),
            'client_name' => $currentClientName ?? $deletedClientName,
            'client_deleted' => $this->user_id === null && is_string($deletedClientName) && $deletedClientName !== '',
            'type' => $this->type ?? $this->asset_type,
            'asset_type' => $this->asset_type,
            'qr_code' => $this->qr_code,
            'has_qr_code' => ! $this->is_ghost && filled($this->qr_code),
            'pallet_name' => $this->pallet_name,
            'reference_code' => $this->reference_code,
            // Older no-QR pallets did not copy their per-entry location onto
            // the pallet. Read it from the linked report so existing mobile
            // pickup lists become useful without a data migration.
            'current_location' => filled($this->current_location) ? $this->current_location : $reportedLocation,
            'notes' => $notes !== '' ? $notes : null,
            'note' => $notes !== '' ? $notes : null,
            'last_status_changed_at' => $this->last_status_changed_at,
            'customer_timer_started_at' => $this->customer_timer_started_at,
            'customer_timer_frozen_at' => $this->customer_timer_frozen_at,
            'days_at_customer' => $this->days_at_customer,
            'grace_days' => $this->grace_days,
            'overdue_days' => $this->overdue_days,
            'debt_eur' => $this->debt_eur,
            'is_active' => $this->is_active,
            'is_ghost' => $this->is_ghost,
            'is_for_repair' => $this->is_for_repair,
            'ghost_pallet_report_id' => $this->ghost_pallet_report_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'current_status' => new StatusResource($this->whenLoaded('currentStatus')),
            'delivery_location' => new DeliveryLocationResource($this->whenLoaded('deliveryLocation')),
        ];
    }
}
