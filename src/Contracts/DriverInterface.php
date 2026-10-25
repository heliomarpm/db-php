<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Contracts;

use HeliomarPM\DbPHP\Results\PaginatedResult;
use HeliomarPM\DbPHP\Results\QueryResult;

interface DriverInterface
{
    /**
     * Executa uma consulta de leitura (SELECT / SHOW / EXPLAIN) e retorna um QueryResult tipado.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): QueryResult;

    /**
     * Executa uma consulta paginada retornando registros e metadados de paginação.
     *
     * @param array<int|string, mixed> $params
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): PaginatedResult;

    /**
     * Executa uma instrução de escrita (INSERT / UPDATE / DELETE / DDL) e retorna o número de linhas afetadas.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Inicia uma transação explícita.
     */
    public function beginTransaction(): void;

    /**
     * Confirma a transação ativa.
     */
    public function commit(): void;

    /**
     * Reverte a transação ativa.
     */
    public function rollback(): void;

    /**
     * Verifica se há uma transação ativa.
     */
    public function inTransaction(): bool;

    /**
     * Executa um callback envelopado em transação atômica com auto-commit e auto-rollback.
     *
     * @template TReturn
     * @param callable(self): TReturn $callback
     * @return TReturn
     */
    public function transaction(callable $callback): mixed;

    /**
     * Retorna o ID gerado pelo último INSERT (chave auto-incremento).
     */
    public function lastInsertId(): int|string;

    /**
     * Retorna a quantidade de linhas afetadas na última operação execute().
     */
    public function countAffected(): int;

    /**
     * Escapa caracteres especiais de uma string para segurança.
     */
    public function escape(string $value): string;

    /**
     * Fecha a conexão com o banco de dados e libera recursos.
     */
    public function close(): void;
}
