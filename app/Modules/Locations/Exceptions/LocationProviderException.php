<?php

namespace App\Modules\Locations\Exceptions;

use RuntimeException;

class LocationProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }
}
