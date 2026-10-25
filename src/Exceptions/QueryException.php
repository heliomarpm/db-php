<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Exceptions;

use Throwable;

class QueryException extends DatabaseException
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        string $message,
        private readonly string $sql,
        private readonly array $params = [],
        private readonly int|string $driverCode = 0,
        ?Throwable $previous = null
    ) {
        $formattedMessage = "Database Query Error: {$message} (Code: {$driverCode})\nSQL: {$sql}";
        if (!empty($params)) {
            $formattedMessage .= "\nParams: " . json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        $codeInt = is_numeric($driverCode) ? (int)$driverCode : 0;
        parent::__construct($formattedMessage, $codeInt, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getDriverCode(): int|string
    {
        return $this->driverCode;
    }
}
