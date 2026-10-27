<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use HeliomarPM\DbPHP\Concerns\PaginatesQueries;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use Throwable;

abstract class AbstractDriver implements DriverInterface
{
    use PaginatesQueries;

    protected int $affectedRows = 0;

    public function __construct(
        protected readonly Config $config
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    public function countAffected(): int
    {
        return $this->affectedRows;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
