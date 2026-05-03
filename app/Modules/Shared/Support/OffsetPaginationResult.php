<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Collection;

readonly class OffsetPaginationResult
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function meta(): array
    {
        return [
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'count' => $this->items->count(),
        ];
    }
}
