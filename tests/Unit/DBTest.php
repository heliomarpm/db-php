<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\DB;
use PHPUnit\Framework\TestCase;

final class DBTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::sqlite(":memory:");
        DB::execute("CREATE TABLE settings (key VARCHAR(50) PRIMARY KEY, value TEXT)");
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        parent::tearDown();
    }

    public function testStaticFacadeQueryAndExecute(): void
    {
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["app.name", "DbPHP Test"]);

        $result = DB::query("SELECT * FROM settings WHERE key = ?", ["app.name"]);
        $this->assertSame(1, $result->count);
        $this->assertSame("DbPHP Test", $result->row["value"]);
    }
    public function testStaticFacadePaginate(): void
    {
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k1", "v1"]);
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k2", "v2"]);
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k3", "v3"]);

        $paginated = DB::paginate("SELECT * FROM settings ORDER BY key ASC", page: 1, perPage: 2);
        $this->assertSame(2, $paginated->count());
        $this->assertSame(3, $paginated->total);
        $this->assertSame(2, $paginated->lastPage);
        $this->assertTrue($paginated->hasMorePages);
        $this->assertSame("k1", $paginated->items[0]["key"]);
    }
}
