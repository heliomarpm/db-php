<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\MysqliDriver;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
            email VARCHAR(100) NOT NULL UNIQUE,
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

    public function testConnectionFailureThrowsConnectionException(): void
    {
        $this->expectException(ConnectionException::class);

        // Porta inexistente/inacessível para forçar falha de conexão imediata
        $invalidConfig = new Config(
            driver: "mysqli",
            host: "127.0.0.1",
            port: 59999,
            database: "mysql",
            username: "root",
            password: "invalid_password"
        );

        $driver = new MysqliDriver($invalidConfig);
        $driver->query("SELECT 1");
    }

    public function testInsertSelectAndLastInsertId(): void
    {
        $this->assertNotNull($this->driver);

        $inserted = $this->driver->execute(
            "INSERT INTO test_users (name, email, active) VALUES (?, ?, ?)",
            ["Alice Silva", "alice@example.com", 1]
        );

        $this->assertSame(1, $inserted);
        $this->assertSame(1, $this->driver->countAffected());

        $lastId = $this->driver->lastInsertId();
        $this->assertGreaterThan(0, (int)$lastId);

        $result = $this->driver->query("SELECT * FROM test_users WHERE email = ?", ["alice@example.com"]);
        $this->assertSame(1, $result->count);
        $this->assertSame("Alice Silva", $result->row["name"]);
        $this->assertGreaterThan(0.0, $result->elapsedTime);
    }

    public function testNamedParametersWithAndWithoutColon(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->execute(
            "INSERT INTO test_users (name, email, active) VALUES (:name, :email, :active)",
            ["name" => "Danilo", "email" => "danilo@example.com", "active" => 1]
        );

        $result1 = $this->driver->query(
            "SELECT * FROM test_users WHERE email = :email",
            ["email" => "danilo@example.com"]
        );
        $this->assertSame(1, $result1->count);
        $this->assertSame("Danilo", $result1->row["name"]);

        $result2 = $this->driver->query(
            "SELECT * FROM test_users WHERE active = :active AND name = :name",
            [":active" => 1, ":name" => "Danilo"]
        );
        $this->assertSame(1, $result2->count);
    }

    public function testBooleanAndNullBinding(): void
    {
        $this->assertNotNull($this->driver);

        // 1. Inserção com booleano true, float e null
        $inserted1 = $this->driver->execute(
            "INSERT INTO test_users (name, email, active, score, bio) VALUES (?, ?, ?, ?, ?)",
            ["Alice Ativa", "alice_bool@test.com", true, 9.75, null]
        );
        $this->assertSame(1, $inserted1);

        // 2. Inserção com booleano false e string
        $inserted2 = $this->driver->execute(
            "INSERT INTO test_users (name, email, active, score, bio) VALUES (?, ?, ?, ?, ?)",
            ["Bob Inativo", "bob_bool@test.com", false, 5.0, "Biografia do Bob"]
        );
        $this->assertSame(1, $inserted2);

        // 3. Consulta com parâmetro nomeado booleano true
        $resTrue = $this->driver->query(
            "SELECT * FROM test_users WHERE active = :active AND name = :name",
            ["active" => true, "name" => "Alice Ativa"]
        );
        $this->assertSame(1, $resTrue->count);
        $this->assertSame("Alice Ativa", $resTrue->row["name"]);
        $this->assertSame(1, (int)$resTrue->row["active"]);
        $this->assertNull($resTrue->row["bio"]);

        // 4. Consulta com parâmetro nomeado booleano false
        $resFalse = $this->driver->query(
            "SELECT * FROM test_users WHERE active = :active AND name = :name",
            ["active" => false, "name" => "Bob Inativo"]
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

    public function testTransactionCommitAndRollback(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->transaction(function (DriverInterface $db): void {
            $db->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Bob", "bob@example.com"]);
        });

        $res = $this->driver->query("SELECT COUNT(*) AS total FROM test_users");
        $this->assertEquals(1, $res->row["total"]);

        try {
            $this->driver->transaction(function (DriverInterface $db): void {
                $db->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Carol", "carol@example.com"]);
                throw new RuntimeException("Erro simulado para rollback");
            });
        } catch (RuntimeException) {
            // rollback esperado
        }

        $resAposRollback = $this->driver->query("SELECT COUNT(*) AS total FROM test_users");
        $this->assertEquals(1, $resAposRollback->row["total"]);
    }

    public function testTransactionDoesNotRollbackWhenQueryErrorHandledInCallback(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Existing", "existing@example.com"]);

        $this->driver->transaction(function (DriverInterface $db): void {
            try {
                // Tenta duplicar chave única
                $db->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Duplicate", "existing@example.com"]);
            } catch (QueryException) {
                // Tratamento local do erro: a transação permanece viva
            }

            $db->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Recovered", "recovered@example.com"]);
        });

        $res = $this->driver->query("SELECT * FROM test_users WHERE email = ?", ["recovered@example.com"]);
        $this->assertSame(1, $res->count);
        $this->assertSame("Recovered", $res->row["name"]);
    }

    public function testManualTransactionCycle(): void
    {
        $this->assertNotNull($this->driver);

        $this->assertFalse($this->driver->inTransaction());

        $this->driver->beginTransaction();
        $this->assertTrue($this->driver->inTransaction());

        $this->driver->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["ManualCommit", "manual_commit@test.com"]);
        $this->driver->commit();
        $this->assertFalse($this->driver->inTransaction());

        $res1 = $this->driver->query("SELECT * FROM test_users WHERE email = ?", ["manual_commit@test.com"]);
        $this->assertSame(1, $res1->count);

        $this->driver->beginTransaction();
        $this->assertTrue($this->driver->inTransaction());

        $this->driver->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["ManualRollback", "manual_rollback@test.com"]);
        $this->driver->rollback();
        $this->assertFalse($this->driver->inTransaction());

        $res2 = $this->driver->query("SELECT * FROM test_users WHERE email = ?", ["manual_rollback@test.com"]);
        $this->assertSame(0, $res2->count);
    }

    public function testInvalidQueryThrowsQueryException(): void
    {
        $this->assertNotNull($this->driver);

        $sql = "SELECT * FROM non_existent_table_xyz_999";

        try {
            $this->driver->query($sql);
            $this->fail("Deveria ter lançado QueryException.");
        } catch (QueryException $e) {
            $this->assertSame($sql, $e->getSql());
            $this->assertGreaterThan(0, $e->getCode());
        }
    }

    public function testPaginateWithPositionalAndNamedParameters(): void
    {
        $this->assertNotNull($this->driver);

        for ($i = 1; $i <= 10; $i++) {
            $this->driver->execute(
                "INSERT INTO test_users (name, email, active) VALUES (?, ?, ?)",
                ["User {$i}", "user{$i}@test.com", $i % 2 === 0 ? 1 : 0]
            );
        }

        // Teste Posicional: página 1 com 3 itens (esperados 5 pares com active = 1)
        $page1 = $this->driver->paginate(
            "SELECT * FROM test_users WHERE active = ? ORDER BY id ASC",
            [1],
            page: 1,
            perPage: 3
        );

        $this->assertSame(3, $page1->count());
        $this->assertSame(5, $page1->total);
        $this->assertSame(2, $page1->lastPage);
        $this->assertTrue($page1->hasMorePages);

        // Teste Nomeado: página 2 com 3 itens
        $page2 = $this->driver->paginate(
            "SELECT * FROM test_users WHERE active = :active ORDER BY id ASC",
            ["active" => 1],
            page: 2,
            perPage: 3
        );

        $this->assertSame(2, $page2->count());
        $this->assertFalse($page2->hasMorePages);
        $this->assertTrue($page2->hasPreviousPages);
    }

    public function testEscape(): void
    {
        $this->assertNotNull($this->driver);

        $escaped = $this->driver->escape("O'Reilly");
        $this->assertSame("O\'Reilly", $escaped);
    }

    public function testCloseRollsBackActiveTransaction(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->beginTransaction();
        $this->driver->execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ["Uncommitted", "uncommitted@test.com"]);
        $this->driver->close();

        // Reconecta para verificar que a transação não finalizada foi revertida
        $this->setUp();
        $this->assertNotNull($this->driver);
        $res = $this->driver->query("SELECT * FROM test_users WHERE email = ?", ["uncommitted@test.com"]);
        $this->assertSame(0, $res->count);
    }

    public function testResolveNamedParametersLexicalParser(): void
    {
        // 1. Preservação de literais com aspas simples, duplas e backticks
        [$sql1, $params1] = MysqliDriver::resolveNamedParameters(
            "SELECT * FROM logs WHERE msg = 'Erro: :id falhou' AND \"Outro: :id\" = 1 AND `col:id` = :id",
            ["id" => 10]
        );
        $this->assertSame(
            "SELECT * FROM logs WHERE msg = 'Erro: :id falhou' AND \"Outro: :id\" = 1 AND `col:id` = ?",
            $sql1
        );
        $this->assertSame([10], $params1);

        // 2. Preservação de aspas escapadas (\' e \") e aspas duplicadas ('' e "")
        [$sql2, $params2] = MysqliDriver::resolveNamedParameters(
            "SELECT * FROM t WHERE a = 'It\'s :param' AND b = 'It''s :param' AND c = \"Dbl \"\" :param\" AND d = :param",
            ["param" => "ok"]
        );
        $this->assertSame(
            "SELECT * FROM t WHERE a = 'It\'s :param' AND b = 'It''s :param' AND c = \"Dbl \"\" :param\" AND d = ?",
            $sql2
        );
        $this->assertSame(["ok"], $params2);

        // 3. Funções de data/hora (DATE_FORMAT com %H:%i:%s) e comentários SQL
        [$sql3, $params3] = MysqliDriver::resolveNamedParameters(
            "SELECT DATE_FORMAT(dt, '%Y-%m-%d %H:%i:%s') AS hora -- comment :param\n/* block :param */ FROM t WHERE id = :id",
            ["id" => 99, "i" => "bad", "s" => "bad", "param" => "bad"]
        );
        $this->assertSame(
            "SELECT DATE_FORMAT(dt, '%Y-%m-%d %H:%i:%s') AS hora -- comment :param\n/* block :param */ FROM t WHERE id = ?",
            $sql3
        );
        $this->assertSame([99], $params3);

        // 4. Parâmetro nomeado repetido (múltiplas ocorrências de :termo)
        [$sql4, $params4] = MysqliDriver::resolveNamedParameters(
            "SELECT * FROM users WHERE (name LIKE :termo OR email LIKE :termo OR bio LIKE :termo) AND role = :role",
            ["termo" => "%dev%", "role" => "admin"]
        );
        $this->assertSame(
            "SELECT * FROM users WHERE (name LIKE ? OR email LIKE ? OR bio LIKE ?) AND role = ?",
            $sql4
        );
        $this->assertSame(["%dev%", "%dev%", "%dev%", "admin"], $params4);

        // 5. Suporte a chaves com prefixo dois-pontos nos parâmetros
        [$sql5, $params5] = MysqliDriver::resolveNamedParameters(
            "SELECT * FROM users WHERE id = :id AND code = :code",
            [":id" => 55, ":code" => "ABC"]
        );
        $this->assertSame("SELECT * FROM users WHERE id = ? AND code = ?", $sql5);
        $this->assertSame([55, "ABC"], $params5);

        // 6. Operadores especiais de banco (:: para typecast e := para atribuição em MySQL)
        [$sql6, $params6] = MysqliDriver::resolveNamedParameters(
            "SELECT @num := :start, col::integer FROM t WHERE id = :id",
            ["start" => 1, "id" => 42]
        );
        $this->assertSame("SELECT @num := ?, col::integer FROM t WHERE id = ?", $sql6);
        $this->assertSame([1, 42], $params6);

        // 7. Parâmetros posicionais ou lista vazia retornam sem alteração
        [$sql7, $params7] = MysqliDriver::resolveNamedParameters("SELECT * FROM t WHERE id = ?", [123]);
        $this->assertSame("SELECT * FROM t WHERE id = ?", $sql7);
        $this->assertSame([123], $params7);
    }

    public function testNamedParametersWithRepeatedKeysAndLiteralsInDatabase(): void
    {
        $this->assertNotNull($this->driver);

        $this->driver->execute("INSERT INTO test_users (name, email, active) VALUES (?, ?, ?)", [
            "Carlos Silva", "carlos@test.com", 1,
        ]);
        $this->driver->execute("INSERT INTO test_users (name, email, active) VALUES (?, ?, ?)", [
            "Mariana Costa", "mariana_silva@test.com", 1,
        ]);
        $this->driver->execute("INSERT INTO test_users (name, email, active) VALUES (?, ?, ?)", [
            "Carlos Eduardo", "eduardo@test.com", 0,
        ]);

        // Busca com parâmetro repetido :termo e literal com dois-pontos
        $result = $this->driver->query(
            "SELECT id, name, email, DATE_FORMAT(NOW(), '%H:%i:%s') AS hora_atual, 'Status: :termo' AS literal_info
             FROM test_users
             WHERE (name LIKE :termo OR email LIKE :termo) AND active = :active
             ORDER BY id ASC",
            [
                "termo" => "%silva%",
                "active" => 1,
            ]
        );

        $this->assertSame(2, $result->count);
        $this->assertSame("Carlos Silva", $result->rows[0]["name"]);
        $this->assertSame("Mariana Costa", $result->rows[1]["name"]);
        $this->assertSame("Status: :termo", $result->rows[0]["literal_info"]);
        $this->assertNotEmpty($result->rows[0]["hora_atual"]);
    }
}
