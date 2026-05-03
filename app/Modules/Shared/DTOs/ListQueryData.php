<?php

namespace App\Modules\Shared\DTOs;

use App\Modules\Shared\Http\Requests\PaginatedIndexRequest;

readonly class ListQueryData
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $limit,
        public int $offset,
        public array $filters,
    ) {
    }

    public static function fromRequest(PaginatedIndexRequest $request): self
    {
        return new self(
            limit: (int) $request->validated('limit', 25),
            offset: (int) $request->validated('offset', 0),
            filters: $request->validatedFilters(),
        );
    }
}
