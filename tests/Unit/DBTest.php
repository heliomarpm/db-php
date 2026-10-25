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
}
