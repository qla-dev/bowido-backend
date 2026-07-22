<?php

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Contracts\LocationProviderInterface;
use App\Modules\Locations\DTOs\ReverseGeocodingResult;
use Illuminate\Support\Facades\Cache;

class ReverseGeocodingService
{
    public function __construct(private readonly LocationProviderInterface $provider) {}

    public function reverseGeocode(float $latitude, float $longitude): ReverseGeocodingResult
    {
        $precision = min(7, max(3, (int) config('location.cache.coordinate_precision', 5)));
        $roundedLatitude = round($latitude, $precision);
        $roundedLongitude = round($longitude, $precision);
        $cacheKey = sprintf(
            'reverse-geocoding:%s:%s:%s',
            $this->provider->name(),
            number_format($roundedLatitude, $precision, '.', ''),
            number_format($roundedLongitude, $precision, '.', ''),
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return ReverseGeocodingResult::fromArray($cached);
        }

        $result = $this->provider->reverseGeocode($latitude, $longitude);
        Cache::put($cacheKey, $result->toArray(), (int) config('location.cache.ttl_seconds', 2592000));

        return $result;
    }
}
