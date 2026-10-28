<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\DB;
use HeliomarPM\DbPHP\Support\QueryLog;
use HeliomarPM\DbPHP\Support\QueryRecord;
use PHPUnit\Framework\TestCase;

final class QueryLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        QueryLog::reset();
        QueryLog::disable();

        DB::sqlite(":memory:");
        DB::execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");
    }

    protected function tearDown(): void
    {
        QueryLog::reset();
        QueryLog::disable();
        DB::disconnect();
        parent::tearDown();
    }

    public function testQueryRecordPropertiesAndSerialization(): void
    {
        $namedRecord = new QueryRecord(
            sql: "SELECT * FROM users WHERE name = :name",
            params: ["name" => "Alice"],
            elapsedTime: 0.00125,
            type: "query",
            driver: "pdo",
            affectedRows: 1,
            connection: "default",
            timestamp: 1700000000.0
        );

        $this->assertTrue($namedRecord->hasParams());
        $this->assertTrue($namedRecord->isNamed());
        $this->assertFalse($namedRecord->isPositional());

        $array = $namedRecord->toArray();
        $this->assertSame("SELECT * FROM users WHERE name = :name", $array["sql"]);
        $this->assertSame(1.25, $array["elapsed_time_ms"]);
        $this->assertTrue($array["is_named"]);
        $this->assertSame("query", $array["type"]);
        $this->assertSame("pdo", $array["driver"]);

        $json = json_encode($namedRecord);
        $this->assertIsString($json);
        $this->assertStringContainsString('"is_named":true', $json);
        $this->assertStringContainsString('"elapsed_time_ms":1.25', $json);

        $posRecord = new QueryRecord(
            sql: "SELECT * FROM users WHERE id = ?",
            params: [1],
            elapsedTime: 0.0005
        );
        $this->assertTrue($posRecord->hasParams());
        $this->assertFalse($posRecord->isNamed());
        $this->assertTrue($posRecord->isPositional());

        $noParamsRecord = new QueryRecord(
            sql: "SELECT * FROM users",
            params: [],
            elapsedTime: 0.0002
        );
        $this->assertFalse($noParamsRecord->hasParams());
        $this->assertFalse($noParamsRecord->isNamed());
        $this->assertFalse($noParamsRecord->isPositional());
    }

    public function testDisabledByDefaultWithoutOverhead(): void
    {
        $this->assertFalse(QueryLog::isEnabled());
        $this->assertFalse(DB::isDebug());

        DB::execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Bob", "bob@example.com"]);
        DB::query("SELECT * FROM users");

        $this->assertSame(0, QueryLog::count());
        $this->assertEmpty(DB::getQueryLog());
    }

    public function testEnableDisableAndFacadeMethods(): void
    {
        QueryLog::enable();
        $this->assertTrue(QueryLog::isEnabled());
        $this->assertTrue(DB::isDebug());

        QueryLog::disable();
        $this->assertFalse(QueryLog::isEnabled());

        DB::setDebug(true);
        $this->assertTrue(DB::isDebug());
        $this->assertTrue(QueryLog::isEnabled());

        DB::setDebug(false);
        $this->assertFalse(DB::isDebug());
    }

    public function testQueryLogCapturesAndAggregatesMetrics(): void
    {
        DB::setDebug(true);

        DB::execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Alice", "alice@example.com"]);
        DB::query("SELECT * FROM users WHERE name = :name", ["name" => "Alice"]);
        DB::query("SELECT * FROM users WHERE id = ?", [1]);
        DB::query("SELECT * FROM users");

        $logs = DB::getQueryLog();
        $this->assertCount(4, $logs);
        $this->assertSame(4, QueryLog::count());

        $this->assertSame("execute", $logs[0]->type);
        $this->assertSame("query", $logs[1]->type);
        $this->assertTrue($logs[1]->isNamed());
        $this->assertTrue($logs[2]->isPositional());
        $this->assertFalse($logs[3]->hasParams());

        $this->assertGreaterThan(0.0, QueryLog::totalTime());

        $metrics = DB::getQueryMetrics();
        $this->assertSame(4, $metrics["total_queries"]);
        $this->assertGreaterThan(0.0, $metrics["total_time"]);
        $this->assertGreaterThan(0.0, $metrics["total_time_ms"]);
        $this->assertSame(1, $metrics["named_queries"]);
        $this->assertSame(2, $metrics["positional_queries"]); // execute(?) e query(?)
        $this->assertSame(1, $metrics["no_param_queries"]);
        $this->assertSame(1, $metrics["queries_by_type"]["execute"]);
        $this->assertSame(3, $metrics["queries_by_type"]["query"]);

        DB::clearQueryLog();
        $this->assertSame(0, QueryLog::count());
        $this->assertEmpty(DB::getQueryLog());
        $this->assertSame(0.0, QueryLog::totalTime());
    }

    public function testConfigLevelDebugActivatesLoggingIndependently(): void
    {
        QueryLog::disable();
        $this->assertFalse(DB::isDebug());

        $debugConfig = Config::forSqlite(":memory:", debug: true);
        $driver = DB::connect($debugConfig, "debug_conn");
        $driver->execute("CREATE TABLE items (id INT, label TEXT)");
        $driver->query("SELECT * FROM items WHERE label = :l", ["l" => "test"]);

        $logs = QueryLog::all();
        $this->assertCount(2, $logs);
        $this->assertInstanceOf(\HeliomarPM\DbPHP\Drivers\AbstractDriver::class, $driver);
        $this->assertSame(":memory:", $driver->getConfig()->database);
    }

    public function testPaginateLogsBothCountAndSelectQueries(): void
    {
        DB::setDebug(true);
        DB::execute("INSERT INTO users (name, email) VALUES (?, ?)", ["U1", "u1@test.com"]);
        DB::execute("INSERT INTO users (name, email) VALUES (?, ?)", ["U2", "u2@test.com"]);
        DB::clearQueryLog();

        DB::paginate("SELECT * FROM users ORDER BY id ASC", page: 1, perPage: 1);

        $logs = DB::getQueryLog();
        $this->assertCount(2, $logs);
        // Primeira query do paginate é o COUNT(*), a segunda é o SELECT paginado com LIMIT/OFFSET
        $this->assertStringContainsString("COUNT(*)", $logs[0]->sql);
        $this->assertStringContainsString("LIMIT", $logs[1]->sql);
    }
}
