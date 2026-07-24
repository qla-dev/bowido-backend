<?php

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Contracts\LocationProviderInterface;
use App\Modules\Locations\DTOs\ReverseGeocodingResult;
use Illuminate\Support\Facades\Cache;

class AddressSearchService
{
    public function __construct(private readonly LocationProviderInterface $provider) {}

    /** @return array<int, ReverseGeocodingResult> */
    public function search(string $query, int $limit = 5): array
    {
        $normalizedQuery = preg_replace('/\s+/', ' ', trim($query)) ?: '';
        $limit = min(10, max(1, $limit));
        $cacheKey = sprintf(
            // Version the key when the normalized address shape changes, so
            // old suggestions with an empty street are not reused.
            'address-search:v2:%s:%s:%d',
            $this->provider->name(),
            sha1(mb_strtolower($normalizedQuery)),
            $limit,
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return array_map(ReverseGeocodingResult::fromArray(...), $cached);
        }

        $results = $this->provider->searchAddress($normalizedQuery, $limit);
        Cache::put(
            $cacheKey,
            array_map(fn (ReverseGeocodingResult $result): array => $result->toArray(), $results),
            (int) config('location.cache.ttl_seconds', 2592000),
        );

        return $results;
    }
}
