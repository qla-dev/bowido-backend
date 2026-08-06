<?php

namespace App\Modules\PalletPhotos\Requests;

use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;
use Illuminate\Validation\Rule;

class ListGalleryPhotosRequest extends PaginatedIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'uploaded_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', Rule::enum(PalletPhotoType::class)],
            'warehouse_scope' => ['nullable', Rule::in(['warehouse_nl', 'warehouse_bih'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }
}
