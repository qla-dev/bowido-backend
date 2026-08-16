<?php

namespace App\Modules\PalletPhotos\Controllers;

use App\Modules\PalletPhotos\Requests\ListGalleryPhotosRequest;
use App\Modules\PalletPhotos\Resources\PalletPhotoResource;
use App\Modules\PalletPhotos\Services\PalletPhotoService;
use App\Modules\Shared\DTOs\ListQueryData;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class GalleryController extends ApiController
{
    public function __construct(private readonly PalletPhotoService $photos) {}

    public function __invoke(ListGalleryPhotosRequest $request): JsonResponse
    {
        if ($request->user()->isCustomer()) {
            return $this->successCollection(
                $this->photos->galleryForCustomer($request->user(), ListQueryData::fromRequest($request)),
                PalletPhotoResource::class,
                __('Customer delivery photos retrieved successfully.'),
            );
        }

        abort_unless($request->user()->hasModulePermission('image_gallery', 'viewAny'), 403);

        return $this->successCollection(
            $this->photos->gallery($request->user(), ListQueryData::fromRequest($request), $request->validated()),
            PalletPhotoResource::class,
            __('Gallery images retrieved successfully.'),
        );
    }
}
