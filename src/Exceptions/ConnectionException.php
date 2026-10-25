<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Exceptions;

use Throwable;

class ConnectionException extends DatabaseException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
