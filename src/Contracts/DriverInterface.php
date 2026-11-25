<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Contracts;

use Generator;
use HeliomarPM\DbPHP\Results\PaginatedResult;
use HeliomarPM\DbPHP\Results\QueryResult;
use HeliomarPM\DbPHP\Results\SimplePaginatedResult;

interface DriverInterface
{
    /**
     * Executa uma consulta de leitura (SELECT / SHOW / EXPLAIN) e retorna um QueryResult tipado.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): QueryResult;

    /**
     * Retorna apenas a primeira linha da consulta ou null se não houver registros.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function row(string $sql, array $params = []): ?array;

    /**
     * Retorna o valor escalar da primeira coluna da primeira linha ou $default se não houver registros.
     *
     * @param array<int|string, mixed> $params
     */
    public function scalar(string $sql, array $params = [], mixed $default = null): mixed;

    /**
     * Retorna uma lista com os valores de uma única coluna (por nome ou índice 0-based).
     *
     * @param array<int|string, mixed> $params
     * @return list<mixed>
     */
    public function column(string $sql, array $params = [], int|string $column = 0): array;

    /**
     * Executa uma consulta de leitura em streaming linha a linha (memória constante O(1)).
     *
     * @param array<int|string, mixed> $params
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    public function cursor(string $sql, array $params = []): Generator;

    /**
     * Executa uma consulta paginada retornando registros e metadados de paginação.
     *
     * @param array<int|string, mixed> $params
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): PaginatedResult;

    /**
     * Executa paginação simples em única consulta (LIMIT perPage + 1), sem COUNT(*).
     *
     * @param array<int|string, mixed> $params
     */
    public function simplePaginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): SimplePaginatedResult;

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
