<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Results\QueryResult;
use PHPUnit\Framework\TestCase;

final class QueryResultTest extends TestCase
{
    public function testQueryResultDataAndTelemetry(): void
    {
        $rows = [
            ["id" => 1, "name" => "Notebook", "price" => 4500.0],
            ["id" => 2, "name" => "Mouse", "price" => 150.0],
        ];

        $result = new QueryResult($rows, 2, 0.00125);

        $this->assertSame(2, $result->count);
        $this->assertSame(2, count($result));
        $this->assertSame($rows[0], $result->row);
        $this->assertSame($rows, $result->rows);
        $this->assertSame(0.00125, $result->elapsedTime);
        $this->assertSame(["Notebook", "Mouse"], $result->column("name"));
    }

    public function testFirstOrDefault(): void
    {
        $rows = [
            ["id" => 10, "status" => "pending"],
            ["id" => 20, "status" => "active"],
        ];

        $result = new QueryResult($rows, 2, 0.0001);

        $active = $result->firstOrDefault(static fn(array $r): bool => $r["status"] === "active");
        $this->assertNotNull($active);
        $this->assertSame(20, $active["id"]);

        $missing = $result->firstOrDefault(static fn(array $r): bool => $r["status"] === "cancelled", ["id" => 0]);
        $this->assertSame(["id" => 0], $missing);
    }
}
