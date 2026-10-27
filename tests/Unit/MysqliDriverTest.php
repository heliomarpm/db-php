<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Drivers\MysqliDriver;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MysqliDriverTest extends TestCase
{
    private ?MysqliDriver $driver = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded("mysqli")) {
            $this->markTestSkipped("Extensão mysqli não está instalada.");
        }

        $host = getenv("DB_MYSQL_HOST") ?: "127.0.0.1";
        $port = (int)(getenv("DB_MYSQL_PORT") ?: 3306);
        $user = getenv("DB_MYSQL_USER") ?: "root";
        $passwords = [getenv("DB_MYSQL_PASS") ?: null, "root", "", "password"];

        foreach ($passwords as $pwd) {
            if ($pwd === null && getenv("DB_MYSQL_PASS") === false) {
                continue;
            }
            try {
                $config = new Config(
                    driver: "mysqli",
                    host: $host,
                    port: $port,
                    database: "mysql",
                    username: $user,
                    password: $pwd ?? "",
                    charset: "utf8mb4"
                );
                $this->driver = new MysqliDriver($config);
                break;
            } catch (Throwable) {
                // Tenta próxima credencial
            }
        }

        if ($this->driver === null) {
            $this->markTestSkipped("Servidor MySQL local não disponível para testes de integração.");
        }

        $this->driver->execute("CREATE TEMPORARY TABLE IF NOT EXISTS test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            score DOUBLE NULL,
            bio TEXT NULL
        )");
        $this->driver->execute("TRUNCATE TABLE test_users");
    }

    protected function tearDown(): void
    {
        if ($this->driver !== null) {
            $this->driver->close();
        }
        parent::tearDown();
    }

    public function testBooleanAndNullBinding(): void
    {
        $this->assertNotNull($this->driver);

        // 1. Inserção com booleano true, float e null
        $inserted1 = $this->driver->execute(
            "INSERT INTO test_users (name, email, active, score, bio) VALUES (?, ?, ?, ?, ?)",
            ["Alice Ativa", "alice@test.com", true, 9.75, null]
        );
        $this->assertSame(1, $inserted1);

        // 2. Inserção com booleano false e string
        $inserted2 = $this->driver->execute(
            "INSERT INTO test_users (name, email, active, score, bio) VALUES (?, ?, ?, ?, ?)",
            ["Bob Inativo", "bob@test.com", false, 5.0, "Biografia do Bob"]
        );
        $this->assertSame(1, $inserted2);

        // 3. Consulta com parâmetro nomeado booleano true
        $resTrue = $this->driver->query(
            "SELECT * FROM test_users WHERE active = :active",
            ["active" => true]
        );
        $this->assertSame(1, $resTrue->count);
        $this->assertSame("Alice Ativa", $resTrue->row["name"]);
        $this->assertSame(1, (int)$resTrue->row["active"]);
        $this->assertNull($resTrue->row["bio"]);

        // 4. Consulta com parâmetro nomeado booleano false
        $resFalse = $this->driver->query(
            "SELECT * FROM test_users WHERE active = :active",
            ["active" => false]
        );
        $this->assertSame(1, $resFalse->count);
        $this->assertSame("Bob Inativo", $resFalse->row["name"]);
        $this->assertSame(0, (int)$resFalse->row["active"]);
        $this->assertSame("Biografia do Bob", $resFalse->row["bio"]);

        // 5. Consulta com IS NULL
        $resNull = $this->driver->query("SELECT * FROM test_users WHERE bio IS NULL");
        $this->assertSame(1, $resNull->count);
        $this->assertSame("Alice Ativa", $resNull->row["name"]);
    }
}
