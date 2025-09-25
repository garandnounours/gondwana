<?php

namespace Gondwana\BookingApi\Exceptions;

use Exception;

class ApiException extends Exception
{
    private ?string $responseBody;

    public function __construct(string $message, ?string $responseBody = null, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
