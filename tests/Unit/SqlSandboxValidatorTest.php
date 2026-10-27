<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use Demo\Exceptions\SecurityException;
use Demo\Support\SqlSandboxValidator;
use PHPUnit\Framework\TestCase;

final class SqlSandboxValidatorTest extends TestCase
{
    public function testAllowsSafeSelectQueries(): void
    {
        $validQueries = [
            'SELECT * FROM produtos WHERE estoque < 20',
            'SELECT id, nome, preco FROM produtos WHERE preco > 50 ORDER BY preco DESC LIMIT 10',
            'SELECT COUNT(*) as total FROM pedidos WHERE status = "pago"',
            'EXPLAIN QUERY PLAN SELECT * FROM produtos',
            'EXPLAIN SELECT 1',
            'WITH cte AS (SELECT id, nome FROM produtos) SELECT * FROM cte',
            'SELECT * FROM produtos;',
            "SELECT `update_time`, updated_at FROM produtos WHERE active = 1",
            "SELECT p.id, p.nome FROM produtos p INNER JOIN categorias c ON c.id = p.categoria_id",
        ];

        foreach ($validQueries as $query) {
            SqlSandboxValidator::assertSafe($query);
            $this->assertTrue(SqlSandboxValidator::isSafe($query));
        }
    }

    public function testAllowsSqlKeywordsAndSemicolonsInsideStringLiterals(): void
    {
        $queriesWithLiterals = [
            "SELECT * FROM logs WHERE mensagem = 'Tentativa de DROP TABLE cancelada;'",
            'SELECT * FROM notas WHERE texto = "Alterar dados com UPDATE;" AND id = 1',
            "SELECT * FROM produtos WHERE descricao = 'Café; Especial com ATTACH e PRAGMA'",
            "SELECT 'SELECT * FROM t; DROP TABLE t;' AS meta_sql",
        ];

        foreach ($queriesWithLiterals as $query) {
            SqlSandboxValidator::assertSafe($query);
            $this->assertTrue(SqlSandboxValidator::isSafe($query));
        }
    }

    public function testAllowsQueriesWithLeadingAndTrailingComments(): void
    {
        $queriesWithComments = [
            "/* comentário em bloco */ SELECT * FROM produtos",
            "-- comentário de linha\nSELECT * FROM clientes",
            "# comentário hash\nSELECT * FROM pedidos",
            "SELECT * FROM categorias /* comentário inline */ WHERE id > 0",
        ];

        foreach ($queriesWithComments as $query) {
            SqlSandboxValidator::assertSafe($query);
            $this->assertTrue(SqlSandboxValidator::isSafe($query));
        }
    }

    public function testBlocksStackedQueries(): void
    {
        $stackedQueries = [
            'SELECT * FROM produtos; DROP TABLE clientes',
            'SELECT * FROM produtos; ATTACH DATABASE "pwn.php" AS pwn',
            'SELECT 1; SELECT 2',
            "SELECT * FROM contas;\nDELETE FROM contas",
        ];

        foreach ($stackedQueries as $query) {
            $this->assertFalse(SqlSandboxValidator::isSafe($query));
            try {
                SqlSandboxValidator::assertSafe($query);
                $this->fail("Deveria ter bloqueado stacked query: {$query}");
            } catch (SecurityException $e) {
                $this->assertStringContainsString('múltiplas consultas concatenadas', $e->getMessage());
            }
        }
    }

    public function testBlocksDangerousSQLiteCommandsAndRceVectors(): void
    {
        $dangerousQueries = [
            "ATTACH DATABASE 'shell.php' AS pwn" => 'Apenas instruções de leitura',
            "/* comentário */ ATTACH DATABASE 'x' AS y" => 'Apenas instruções de leitura',
            "DETACH DATABASE pwn" => 'Apenas instruções de leitura',
            "PRAGMA compile_options" => 'Apenas instruções de leitura',
            "PRAGMA database_list" => 'Apenas instruções de leitura',
            "SELECT load_extension('evil.so')" => 'LOAD_EXTENSION',
            "SELECT writefile('shell.php', 'data')" => 'WRITEFILE',
            "SELECT readfile('/etc/passwd')" => 'READFILE',
            "VACUUM" => 'Apenas instruções de leitura',
            "REINDEX" => 'Apenas instruções de leitura',
        ];

        foreach ($dangerousQueries as $query => $expectedErrorPart) {
            $this->assertFalse(SqlSandboxValidator::isSafe($query));
            try {
                SqlSandboxValidator::assertSafe($query);
                $this->fail("Deveria ter bloqueado comando perigoso: {$query}");
            } catch (SecurityException $e) {
                $this->assertStringContainsString($expectedErrorPart, $e->getMessage());
            }
        }
    }

    public function testBlocksDestructiveDmlAndDdlCommands(): void
    {
        $destructiveQueries = [
            'DROP TABLE produtos',
            'DELETE FROM clientes WHERE id = 1',
            'UPDATE contas SET saldo = 0',
            'INSERT INTO produtos (nome) VALUES ("teste")',
            'ALTER TABLE produtos ADD COLUMN hack TEXT',
            'CREATE TABLE evil (id INT)',
            'REPLACE INTO produtos (id) VALUES (1)',
            'TRUNCATE TABLE logs',
            'WITH cte AS (SELECT 1) DELETE FROM produtos',
            'WITH cte AS (SELECT 1) UPDATE produtos SET preco = 0',
        ];

        foreach ($destructiveQueries as $query) {
            $this->assertFalse(SqlSandboxValidator::isSafe($query));
            $this->expectException(SecurityException::class);
            SqlSandboxValidator::assertSafe($query);
        }
    }

    public function testRejectsEmptyAndWhitespaceOnlyQueries(): void
    {
        $this->assertFalse(SqlSandboxValidator::isSafe(''));
        $this->assertFalse(SqlSandboxValidator::isSafe('   '));
        $this->assertFalse(SqlSandboxValidator::isSafe("/* apenas comentário */"));

        $this->expectException(SecurityException::class);
        SqlSandboxValidator::assertSafe('');
    }

    public function testRejectsQueriesExceedingMaxLength(): void
    {
        $oversizedQuery = 'SELECT ' . str_repeat('a', SqlSandboxValidator::MAX_SQL_LENGTH + 10);
        $this->assertFalse(SqlSandboxValidator::isSafe($oversizedQuery));

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('tamanho máximo permitido');
        SqlSandboxValidator::assertSafe($oversizedQuery);
    }
}
