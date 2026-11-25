<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\PdoDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class PdoMysqlDriverTest extends TestCase
{
    private ?PdoDriver $driver = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded("pdo_mysql")) {
            $this->markTestSkipped("Extensão pdo_mysql não está instalada.");
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
                    driver: "mysql",
                    host: $host,
                    port: $port,
                    database: "mysql",
                    username: $user,
                    password: $pwd ?? "",
                    charset: "utf8mb4",
                    collation: "utf8mb4_unicode_ci"
                );
                $this->driver = new PdoDriver($config);
                break;
            } catch (Throwable) {
                // Tenta próxima credencial
            }
        }

        if ($this->driver === null) {
            $this->markTestSkipped("Servidor MySQL local não disponível para testes PDO MySQL.");
        }

        $this->driver->execute("CREATE TEMPORARY TABLE IF NOT EXISTS pdo_test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            active TINYINT(1) NOT NULL DEFAULT 1
        )");
        $this->driver->execute("TRUNCATE TABLE pdo_test_users");
    }

    protected function tearDown(): void
    {
        if ($this->driver !== null) {
            $this->driver->close();
        }
        parent::tearDown();
    }

    public function testCustomCollationIsAppliedOnPdoMysqlConnection(): void
    {
        $this->assertNotNull($this->driver);
        $cfg = $this->driver->getConfig();

        $customDriver = new PdoDriver(new Config(
            driver: "mysql",
            host: $cfg->host,
            port: $cfg->port,
            database: $cfg->database,
            username: $cfg->username,
            password: $cfg->password,
            charset: "utf8mb4",
            collation: "utf8mb4_bin"
        ));

        try {
            $res = $customDriver->query("SELECT @@collation_connection AS current_collation");
            $this->assertSame("utf8mb4_bin", $res->row["current_collation"]);
        } finally {
            $customDriver->close();
        }
    }

    public function testInsertSelectNamedAndPaginateOnMysql(): void
    {
        $this->assertNotNull($this->driver);

        for ($i = 1; $i <= 5; $i++) {
            $this->driver->execute(
                "INSERT INTO pdo_test_users (name, email, active) VALUES (:name, :email, :active)",
                ["name" => "User {$i}", "email" => "pdo_u{$i}@test.com", "active" => 1]
            );
        }

        $lastId = $this->driver->lastInsertId();
        $this->assertGreaterThan(0, (int)$lastId);

        $paginated = $this->driver->paginate(
            "SELECT * FROM pdo_test_users WHERE active = :active ORDER BY id ASC",
            ["active" => 1],
            page: 1,
            perPage: 2
        );

        $this->assertSame(5, $paginated->total);
        $this->assertSame(2, $paginated->count());
        $this->assertSame(3, $paginated->lastPage);
    }

    public function testNestedTransactionsWithSavepointOnMysql(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->transaction(function (DriverInterface $db): void {
            $db->execute("INSERT INTO pdo_test_users (name, email) VALUES (?, ?)", ["Outer", "outer_pdo@test.com"]);

            try {
                $db->transaction(function (DriverInterface $inner): void {
                    $inner->execute("INSERT INTO pdo_test_users (name, email) VALUES (?, ?)", ["Inner", "inner_pdo@test.com"]);
                    throw new RuntimeException("Falha no savepoint");
                });
            } catch (RuntimeException) {
                // Savepoint revertido
            }
        });

        $res = $this->driver->query("SELECT name FROM pdo_test_users");
        $this->assertSame(1, $res->count);
        $this->assertSame("Outer", $res->row["name"]);
    }
}
