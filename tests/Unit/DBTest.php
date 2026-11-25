<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\DB;
use InvalidArgumentException;
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

    public function testSetDefaultConnectionAndSwitching(): void
    {
        $this->assertSame("default", DB::getDefaultConnection());

        // Conecta uma base secundária em memória com nome 'analytics'
        DB::sqlite(":memory:", name: "analytics");
        DB::connection("analytics")->execute("CREATE TABLE logs (event VARCHAR(50))");
        DB::connection("analytics")->execute("INSERT INTO logs VALUES (?)", ["user.login"]);

        // Muda a conexão padrão para 'analytics'
        DB::setDefaultConnection("analytics");
        $this->assertSame("analytics", DB::getDefaultConnection());

        // Chamada estática DB::query() deve agora consultar a base 'analytics'
        $result = DB::query("SELECT event FROM logs");
        $this->assertSame(1, $result->count);
        $this->assertSame("user.login", $result->row["event"]);

        // Retorna para default
        DB::setDefaultConnection("default");
        $defaultResult = DB::query("SELECT COUNT(*) as total FROM settings");
        $this->assertArrayHasKey("total", $defaultResult->row);

        // Desconectar tudo deve restaurar defaultConnection para 'default'
        DB::disconnect();
        $this->assertSame("default", DB::getDefaultConnection());
    }

    public function testSetDefaultConnectionThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("O nome da conexão padrão não pode ser vazio.");

        DB::setDefaultConnection("   ");
    }

    public function testManualTransactionCommit(): void
    {
        $this->assertFalse(DB::inTransaction());

        DB::beginTransaction();
        $this->assertTrue(DB::inTransaction());

        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["tx.commit", "success"]);
        DB::commit();

        $this->assertFalse(DB::inTransaction());

        $res = DB::query("SELECT value FROM settings WHERE key = ?", ["tx.commit"]);
        $this->assertSame(1, $res->count);
        $this->assertSame("success", $res->row["value"]);
    }

    public function testManualTransactionRollback(): void
    {
        $this->assertFalse(DB::inTransaction());

        DB::beginTransaction();
        $this->assertTrue(DB::inTransaction());

        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["tx.rollback", "fail"]);
        DB::rollback();

        $this->assertFalse(DB::inTransaction());

        $res = DB::query("SELECT value FROM settings WHERE key = ?", ["tx.rollback"]);
        $this->assertSame(0, $res->count);
    }

    public function testStaticFacadeCursor(): void
    {
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["c1", "v1"]);
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["c2", "v2"]);

        $keys = [];
        foreach (DB::cursor("SELECT key FROM settings ORDER BY key ASC") as $row) {
            $keys[] = $row["key"];
        }

        $this->assertSame(["c1", "c2"], $keys);
    }

    public function testStaticFacadeRowScalarColumnAndSimplePaginate(): void
    {
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k1", "v1"]);
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k2", "v2"]);
        DB::execute("INSERT INTO settings (key, value) VALUES (?, ?)", ["k3", "v3"]);

        $row = DB::row("SELECT * FROM settings WHERE key = ?", ["k2"]);
        $this->assertSame(["key" => "k2", "value" => "v2"], $row);
        $this->assertNull(DB::row("SELECT * FROM settings WHERE key = ?", ["missing"]));

        $count = DB::scalar("SELECT COUNT(*) FROM settings");
        $this->assertSame(3, (int)$count);
        $this->assertSame("fallback", DB::scalar("SELECT value FROM settings WHERE key = ?", ["missing"], "fallback"));

        $this->assertSame(["k1", "k2", "k3"], DB::column("SELECT key, value FROM settings ORDER BY key ASC"));
        $this->assertSame(["v1", "v2", "v3"], DB::column("SELECT key, value FROM settings ORDER BY key ASC", [], "value"));

        $page1 = DB::simplePaginate("SELECT * FROM settings ORDER BY key ASC", page: 1, perPage: 2);
        $this->assertSame(2, $page1->count());
        $this->assertTrue($page1->hasMorePages);
        $this->assertFalse($page1->hasPreviousPages);
        $this->assertSame(1, $page1->from);
        $this->assertSame(2, $page1->to);

        $page2 = DB::simplePaginate("SELECT * FROM settings ORDER BY key ASC", page: 2, perPage: 2);
        $this->assertSame(1, $page2->count());
        $this->assertFalse($page2->hasMorePages);
        $this->assertTrue($page2->hasPreviousPages);
    }
}
