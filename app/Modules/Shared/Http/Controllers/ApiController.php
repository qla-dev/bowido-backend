<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Support\OffsetPaginationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    public function authorize($ability, $arguments = [])
    {
        $response = Gate::inspect($ability, $arguments);
        $message = $response->message();

        abort_if(
            $response->denied(),
            Response::HTTP_FORBIDDEN,
            is_string($message) && $message !== '' ? $message : __('This action is unauthorized.'),
        );

        return $response;
    }

    protected function success(
        mixed $data,
        string $message,
        array $meta = [],
        array $errors = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    protected function successCollection(
        OffsetPaginationResult $result,
        string $resourceClass,
        string $message,
    ): JsonResponse {
        return $this->success(
            data: $resourceClass::collection($result->items)->resolve(),
            message: $message,
            meta: $result->meta(),
        );
    }

    protected function successItem(
        Model $model,
        string $resourceClass,
        string $message,
        int $status = 200,
    ): JsonResponse {
        /** @var JsonResource $resource */
        $resource = new $resourceClass($model);

        return $this->success(
            data: $resource->resolve(),
            message: $message,
            status: $status,
        );
    }
}
