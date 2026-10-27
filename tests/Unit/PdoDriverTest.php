<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\PdoDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
                throw new RuntimeException("Erro simulado para rollback");
            });
        } catch (RuntimeException) {
            // rollback esperado
        }

        $resAposRollback = $this->driver->query("SELECT COUNT(*) AS total FROM users");
        $this->assertEquals(1, $resAposRollback->row["total"]);
    }

    public function testTransactionDoesNotRollbackWhenQueryErrorHandledInCallback(): void
    {
        $this->driver->execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Existing", "existing@example.com"]);

        $this->driver->transaction(function (DriverInterface $db): void {
            try {
                $db->execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Duplicate", "existing@example.com"]);
            } catch (\HeliomarPM\DbPHP\Exceptions\QueryException) {
                // Tratamento local do erro: a transação permanece viva
            }

            $db->execute("INSERT INTO users (name, email) VALUES (?, ?)", ["Recovered", "recovered@example.com"]);
        });

        $res = $this->driver->query("SELECT * FROM users WHERE email = ?", ["recovered@example.com"]);
        $this->assertSame(1, $res->count);
        $this->assertSame("Recovered", $res->row["name"]);
    }

    public function testNamedParametersWithAndWithoutColon(): void
    {
        $this->driver->execute(
            "INSERT INTO users (name, email, active) VALUES (:name, :email, :active)",
            ["name" => "Danilo", "email" => "danilo@example.com", "active" => 1]
        );

        $result1 = $this->driver->query(
            "SELECT * FROM users WHERE email = :email",
            ["email" => "danilo@example.com"]
        );
        $this->assertSame(1, $result1->count);
        $this->assertSame("Danilo", $result1->row["name"]);

        $result2 = $this->driver->query(
            "SELECT * FROM users WHERE active = :active AND name = :name",
            [":active" => 1, ":name" => "Danilo"]
        );
        $this->assertSame(1, $result2->count);
    }
    public function testPaginateWithPositionalAndNamedParameters(): void
    {
        for ($i = 2; $i <= 12; $i++) {
            $this->driver->execute(
                "INSERT INTO users (name, email, active) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@example.com", $i % 2 === 0 ? 1 : 0]
            );
        }

        // Teste Posicional: página 1 com 5 itens
        $page1 = $this->driver->paginate(
            "SELECT * FROM users WHERE active = ? ORDER BY id ASC",
            [1],
            page: 1,
            perPage: 5
        );

        $this->assertSame(5, $page1->count());
        $this->assertSame(6, $page1->total);
        $this->assertSame(2, $page1->lastPage);
        $this->assertTrue($page1->hasMorePages);

        // Teste Nomeado: página 2 com 5 itens
        $page2 = $this->driver->paginate(
            "SELECT * FROM users WHERE active = :active ORDER BY id ASC",
            ["active" => 1],
            page: 2,
            perPage: 5
        );

        $this->assertSame(1, $page2->count());
        $this->assertFalse($page2->hasMorePages);
        $this->assertTrue($page2->hasPreviousPages);
    }

    public function testRemoveTrailingOrderBy(): void
    {
        // 1. Caso simples
        $sql1 = "SELECT * FROM users ORDER BY id DESC";
        $this->assertSame("SELECT * FROM users", $this->driver->removeTrailingOrderBy($sql1));

        // 2. Múltiplas colunas com direções variadas
        $sql2 = "SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC, id DESC";
        $this->assertSame("SELECT id, name FROM users WHERE active = 1", $this->driver->removeTrailingOrderBy($sql2));

        // 3. Múltiplas linhas e espaços variados
        $sql3 = "SELECT * FROM users\n    ORDER   BY\n    id ASC";
        $this->assertSame("SELECT * FROM users", $this->driver->removeTrailingOrderBy($sql3));

        // 4. Preserva ORDER BY interno a subquery
        $sql4 = "SELECT id, (SELECT name FROM roles ORDER BY priority DESC) FROM users ORDER BY id ASC";
        $this->assertSame(
            "SELECT id, (SELECT name FROM roles ORDER BY priority DESC) FROM users",
            $this->driver->removeTrailingOrderBy($sql4)
        );

        // 5. Preserva ORDER BY dentro de string literal
        $sql5 = "SELECT * FROM logs WHERE message = 'Favor ORDER BY data' ORDER BY id DESC";
        $this->assertSame(
            "SELECT * FROM logs WHERE message = 'Favor ORDER BY data'",
            $this->driver->removeTrailingOrderBy($sql5)
        );

        // 6. Consulta sem ORDER BY permanece inalterada
        $sql6 = "SELECT * FROM users WHERE active = 1";
        $this->assertSame($sql6, $this->driver->removeTrailingOrderBy($sql6));

        // 7. Consulta com GROUP BY e HAVING preserva ambos e remove apenas o ORDER BY
        $sql7 = "SELECT active, COUNT(*) FROM users GROUP BY active HAVING COUNT(*) > 1 ORDER BY active DESC";
        $this->assertSame(
            "SELECT active, COUNT(*) FROM users GROUP BY active HAVING COUNT(*) > 1",
            $this->driver->removeTrailingOrderBy($sql7)
        );
    }

    public function testPaginateWithGroupByAndOrderBy(): void
    {
        $this->driver->execute("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ["U1", "u1@test.com", 1]);
        $this->driver->execute("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ["U2", "u2@test.com", 1]);
        $this->driver->execute("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ["U3", "u3@test.com", 0]);

        $paginated = $this->driver->paginate(
            "SELECT active, COUNT(*) AS total_por_status FROM users GROUP BY active ORDER BY active DESC",
            [],
            page: 1,
            perPage: 10
        );

        $this->assertSame(2, $paginated->total);
        $this->assertSame(2, $paginated->count());
        $this->assertSame(1, $paginated->lastPage);
    }
}
