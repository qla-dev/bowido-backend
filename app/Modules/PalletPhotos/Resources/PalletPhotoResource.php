<?php

namespace App\Modules\PalletPhotos\Resources;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Older delivery photos predate the audit-backed status fields. Read
        // their transition from the same source as newly stored photos so the
        // UI never reports the current status as both old and new.
        $deliveryAudit = in_array($this->type, [PalletPhotoType::DeliveryPhoto, PalletPhotoType::Scan], true)
            && $this->client_id !== null
            ? AuditLog::query()
                ->with(['oldStatus', 'newStatus'])
                ->where('pallet_id', $this->pallet_id)
                ->where('new_client_id', $this->client_id)
                ->whereHas('newStatus', fn ($query) => $query->where('slug', 'bij-de-klant'))
                ->whereHas('oldStatus', fn ($query) => $query->whereIn('slug', ['bowido-nl', 'onbekend']))
                ->latest('created_at')
                ->latest('id')
                ->first()
            : null;
        $oldStatus = $deliveryAudit?->oldStatus ?? $this->oldStatus;
        $newStatus = $deliveryAudit?->newStatus ?? $this->newStatus;
        $photoStatus = $newStatus ?? $oldStatus ?? $this->pallet?->currentStatus;

        return [
            'id' => $this->id,
            'pallet_id' => $this->pallet_id,
            'old_status_id' => $deliveryAudit?->old_status_id ?? $this->old_status_id,
            'new_status_id' => $deliveryAudit?->new_status_id ?? $this->new_status_id,
            'client_id' => $this->client_id,
            'service_report_id' => $this->service_report_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'type' => $this->type?->value,
            'delivery_started_at' => $this->delivery_started_at,
            'warehouse_scope' => $this->warehouse_scope,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'expires_at' => $this->expires_at,
            'url' => $this->expires_at?->isFuture()
                ? route('pallet-photos.file', ['palletPhoto' => $this->id])
                : null,
            'created_at' => $this->created_at,
            'status' => $photoStatus ? [
                'id' => $photoStatus->id,
                'name' => $photoStatus->name,
                'slug' => $photoStatus->slug,
            ] : null,
            'pallet' => $this->whenLoaded('pallet', fn (): array => [
                'id' => $this->pallet->id,
                'qr_code' => $this->pallet->qr_code,
                'name' => $this->pallet->pallet_name ?? $this->pallet->reference_code ?? $this->pallet->qr_code,
                'customer' => $this->pallet->user?->customerDetail?->company_name ?? $this->pallet->user?->name,
                'status' => $this->pallet->currentStatus?->name,
            ]),
            'uploader' => $this->whenLoaded('uploadedByUser', fn (): array => [
                'id' => $this->uploadedByUser->id,
                'name' => $this->uploadedByUser->name,
                'role' => $this->uploadedByUser->role?->name,
            ]),
        ];
    }
}
