<?php

namespace App\Modules\PalletPhotos\Controllers;

use App\Modules\CustomerDetails\Models\CustomerDetail;
use App\Modules\PalletPhotos\Enums\PalletPhotoType;
use App\Modules\PalletPhotos\Models\PalletPhoto;
use App\Modules\PalletPhotos\Requests\ListCustomerPalletPhotosRequest;
use App\Modules\PalletPhotos\Requests\StorePalletPhotoRequest;
use App\Modules\PalletPhotos\Resources\PalletPhotoResource;
use App\Modules\PalletPhotos\Services\PalletPhotoService;
use App\Modules\Pallets\Models\Pallet;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class PalletPhotoController extends ApiController
{
    public function __construct(private readonly PalletPhotoService $palletPhotoService) {}

    public function store(StorePalletPhotoRequest $request, Pallet $pallet): JsonResponse
    {
        $this->authorize('update', $pallet);

        $attributes = $request->validated();

        $photo = $this->palletPhotoService->store(
            pallet: $pallet,
            actor: $request->user(),
            image: $request->file('image'),
            type: PalletPhotoType::Scan,
            oldStatusId: $attributes['old_status_id'] ?? null,
            newStatusId: $attributes['new_status_id'] ?? null,
            clientId: $attributes['client_id'] ?? null,
        );

        return $this->successItem($photo, PalletPhotoResource::class, __('Pallet photo uploaded successfully.'), 201);
    }

    public function forCustomer(ListCustomerPalletPhotosRequest $request, CustomerDetail $customerDetail): JsonResponse
    {
        $this->authorize('view', $customerDetail);

        return $this->successCollection(
            $this->palletPhotoService->forCustomer($customerDetail->user_id, ListQueryData::fromRequest($request)),
            PalletPhotoResource::class,
            __('Customer pallet photos retrieved successfully.'),
        );
    }

    public function destroy(PalletPhoto $palletPhoto): JsonResponse
    {
        $palletPhoto->loadMissing('pallet');
        $actor = request()->user();

        abort_unless(
            $actor->isAdmin() || $palletPhoto->uploaded_by_user_id === $actor->id,
            403,
            __('You can only delete photos that you uploaded.'),
        );
        $this->authorize('update', $palletPhoto->pallet);

        $this->palletPhotoService->delete($palletPhoto);

        return $this->success(null, __('Pallet photo deleted successfully.'));
    }

    public function file(PalletPhoto $palletPhoto): StreamedResponse|Response
    {
        $palletPhoto->loadMissing('pallet');
        abort_unless($this->palletPhotoService->canAccess(request()->user(), $palletPhoto), 403);
        abort_if($palletPhoto->expires_at->isPast(), 410, __('This photo has expired.'));
        if ($palletPhoto->content !== null) {
            return response($palletPhoto->content, 200, [
                'Content-Type' => $palletPhoto->mime_type,
                'Content-Disposition' => 'inline; filename="'.$palletPhoto->original_name.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        abort_unless($palletPhoto->path !== null && $palletPhoto->disk !== null && Storage::disk($palletPhoto->disk)->exists($palletPhoto->path), 404, __('Photo not found.'));

        return Storage::disk($palletPhoto->disk)->response(
            $palletPhoto->path,
            $palletPhoto->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
