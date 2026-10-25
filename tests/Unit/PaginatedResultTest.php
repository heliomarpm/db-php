<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Results\PaginatedResult;
use PHPUnit\Framework\TestCase;

final class PaginatedResultTest extends TestCase
{
    public function testEmptyPagination(): void
    {
        $paginated = new PaginatedResult(
            items: [],
            total: 0,
            perPage: 10,
            currentPage: 1,
            elapsedTime: 0.001
        );

        $this->assertSame(0, $paginated->count());
        $this->assertTrue($paginated->isEmpty());
        $this->assertFalse($paginated->isNotEmpty());
        $this->assertSame(1, $paginated->lastPage);
        $this->assertNull($paginated->from);
        $this->assertNull($paginated->to);
        $this->assertFalse($paginated->hasMorePages);
        $this->assertFalse($paginated->hasPreviousPages);
    }

    public function testPaginationCalculations(): void
    {
        $itemsPage2 = [
            ["id" => 11, "name" => "Item 11"],
            ["id" => 12, "name" => "Item 12"],
            ["id" => 13, "name" => "Item 13"],
            ["id" => 14, "name" => "Item 14"],
            ["id" => 15, "name" => "Item 15"],
            ["id" => 16, "name" => "Item 16"],
            ["id" => 17, "name" => "Item 17"],
            ["id" => 18, "name" => "Item 18"],
            ["id" => 19, "name" => "Item 19"],
            ["id" => 20, "name" => "Item 20"],
        ];

        $paginated = new PaginatedResult(
            items: $itemsPage2,
            total: 45,
            perPage: 10,
            currentPage: 2,
            elapsedTime: 0.0025
        );

        $this->assertSame(10, $paginated->count());
        $this->assertFalse($paginated->isEmpty());
        $this->assertSame(45, $paginated->total);
        $this->assertSame(10, $paginated->perPage);
        $this->assertSame(2, $paginated->currentPage);
        $this->assertSame(5, $paginated->lastPage);
        $this->assertSame(11, $paginated->from);
        $this->assertSame(20, $paginated->to);
        $this->assertTrue($paginated->hasMorePages);
        $this->assertTrue($paginated->hasPreviousPages);
    }

    public function testColumnAndFirstOrDefault(): void
    {
        $items = [
            ["id" => 1, "name" => "Primeiro"],
            ["id" => 2, "name" => "Segundo"],
        ];

        $paginated = new PaginatedResult(items: $items, total: 2, perPage: 10, currentPage: 1);

        $this->assertSame(["Primeiro", "Segundo"], $paginated->column("name"));

        $encontrado = $paginated->firstOrDefault(fn(array $row): bool => (int)$row["id"] === 2);
        $this->assertSame("Segundo", $encontrado["name"] ?? null);

        $naoEncontrado = $paginated->firstOrDefault(fn(array $row): bool => (int)$row["id"] === 99, default: null);
        $this->assertNull($naoEncontrado);
    }

    public function testJsonSerialization(): void
    {
        $paginated = new PaginatedResult(
            items: [["id" => 1, "name" => "Alfa"]],
            total: 1,
            perPage: 10,
            currentPage: 1,
            elapsedTime: 0.0005
        );

        $json = json_encode($paginated);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey("data", $decoded);
        $this->assertArrayHasKey("pagination", $decoded);
        $this->assertSame(1, $decoded["pagination"]["total"]);
        $this->assertSame(1, $decoded["pagination"]["current_page"]);
    }
}
