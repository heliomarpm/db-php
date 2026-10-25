<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\PdoDriver;
use PHPUnit\Framework\TestCase;

final class PdoDriverTest extends TestCase
{
    private PdoDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new PdoDriver(Config::forSqlite(":memory:"));

        $this->driver->execute("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            active TINYINT(1) NOT NULL DEFAULT 1
        )");
    }

    protected function tearDown(): void
    {
        $this->driver->close();
        parent::tearDown();
    }

    public function testInsertAndSelect(): void
    {
        $inserted = $this->driver->execute(
            "INSERT INTO users (name, email, active) VALUES (?, ?, ?)",
            ["Alice Silva", "alice@example.com", 1]
        );

        $this->assertSame(1, $inserted);
        $this->assertSame(1, $this->driver->lastInsertId());

        $result = $this->driver->query("SELECT * FROM users WHERE email = ?", ["alice@example.com"]);
        $this->assertSame(1, $result->count);
        $this->assertSame("Alice Silva", $result->row["name"]);
        $this->assertGreaterThan(0.0, $result->elapsedTime);
    }

    public function testTransactionCommitAndRollback(): void
    {
        $this->driver->transaction(function (DriverInterface $db): void {
            $db->execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Bob", "bob@example.com"]);
        });

        $res = $this->driver->query("SELECT COUNT(*) AS total FROM users");
        $this->assertEquals(1, $res->row["total"]);

        try {
            $this->driver->transaction(function (DriverInterface $db): void {
                $db->execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Carol", "carol@example.com"]);
                throw new \RuntimeException("Erro simulado para rollback");
            });
        } catch (\RuntimeException) {
            // rollback esperado
        }

        $resAposRollback = $this->driver->query("SELECT COUNT(*) AS total FROM users");
        $this->assertEquals(1, $resAposRollback->row["total"]);
    }
}
