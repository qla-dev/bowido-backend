<?php

namespace App\Modules\Locations\Contracts;

use App\Modules\Locations\DTOs\ReverseGeocodingResult;

interface LocationProviderInterface
{
    public function name(): string;

    public function reverseGeocode(float $latitude, float $longitude): ReverseGeocodingResult;
}
