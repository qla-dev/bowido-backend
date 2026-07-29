<?php

namespace App\Modules\Auth\Exceptions;

use RuntimeException;

class KvkLookupException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }
}
