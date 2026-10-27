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
            ['id' => 1, 'name' => 'Notebook', 'price' => 4500.0],
            ['id' => 2, 'name' => 'Mouse', 'price' => 150.0],
        ];

        $result = new QueryResult($rows, 2, 0.00125);

        $this->assertSame(2, $result->count);
        $this->assertSame(2, count($result));
        $this->assertSame($rows[0], $result->row);
        $this->assertSame($rows, $result->rows);
        $this->assertSame(0.00125, $result->elapsedTime);
        $this->assertSame(['Notebook', 'Mouse'], $result->column('name'));
    }

    public function testCountIsCalculatedAutomaticallyIgnoringExplicitParam(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 3, 'name' => 'C'],
        ];

        // Passa $count=0 de propósito — deve ser ignorado e calculado como 3
        $result = new QueryResult($rows, 0);

        $this->assertSame(3, $result->count);
        $this->assertSame(3, count($result));
    }

    public function testEmptyResultHasZeroCountAndNullRow(): void
    {
        $result = new QueryResult([]);

        $this->assertSame(0, $result->count);
        $this->assertSame(0, count($result));
        $this->assertSame([], $result->row);
        $this->assertSame([], $result->rows);
    }

    public function testFirstOrDefault(): void
    {
        $rows = [
            ['id' => 10, 'status' => 'pending'],
            ['id' => 20, 'status' => 'active'],
        ];

        $result = new QueryResult($rows, 2, 0.0001);

        $active = $result->firstOrDefault(static fn(array $r): bool => $r['status'] === 'active');
        $this->assertNotNull($active);
        $this->assertSame(20, $active['id']);

        $missing = $result->firstOrDefault(static fn(array $r): bool => $r['status'] === 'cancelled', ['id' => 0]);
        $this->assertSame(['id' => 0], $missing);

        // Sem predicado: retorna o primeiro
        $first = $result->firstOrDefault();
        $this->assertSame(10, $first['id']);
    }

    public function testFirstOrDefaultOnEmptyResult(): void
    {
        $result = new QueryResult([]);

        $default = ['fallback' => true];
        $this->assertSame($default, $result->firstOrDefault(null, $default));
        $this->assertNull($result->firstOrDefault());
    }

    public function testColumnExtractsValuesFromAllRows(): void
    {
        $rows = [
            ['id' => 1, 'sku' => 'A001'],
            ['id' => 2, 'sku' => 'A002'],
            ['id' => 3, 'sku' => 'A003'],
        ];

        $result = new QueryResult($rows);

        $this->assertSame([1, 2, 3], $result->column('id'));
        $this->assertSame(['A001', 'A002', 'A003'], $result->column('sku'));
        $this->assertSame([], $result->column('nonexistent'));
    }

    public function testJsonSerializationStructure(): void
    {
        $rows = [['id' => 1, 'name' => 'Test']];
        $result = new QueryResult($rows, 1, 0.0005);

        $json = json_encode($result);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('count', $decoded);
        $this->assertArrayHasKey('elapsed_time', $decoded);
        $this->assertArrayHasKey('row', $decoded);
        $this->assertArrayHasKey('rows', $decoded);
        $this->assertSame(1, $decoded['count']);
        $this->assertSame(0.0005, $decoded['elapsed_time']);
    }

    public function testArrayAccessWithIntOffsetReturnsRow(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
        ];
        $result = new QueryResult($rows);

        $this->assertSame($rows[0], $result[0]);
        $this->assertSame($rows[1], $result[1]);
        $this->assertNull($result[99]);
    }

    public function testArrayAccessWithStringOffsetReturnsFirstRowField(): void
    {
        $rows = [['id' => 42, 'name' => 'Gamma']];
        $result = new QueryResult($rows);

        $this->assertSame(42, $result['id']);
        $this->assertSame('Gamma', $result['name']);
    }

    public function testIterationYieldsAllRows(): void
    {
        $rows = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $result = new QueryResult($rows);

        $collected = [];
        foreach ($result as $row) {
            $collected[] = $row;
        }

        $this->assertSame($rows, $collected);
    }
}
