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
}
