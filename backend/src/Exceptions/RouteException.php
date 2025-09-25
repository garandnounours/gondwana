<?php

namespace Gondwana\BookingApi\Exceptions;

use Exception;

class RouteException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
