<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Results;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Traversable;

/**
 * Objeto de resultado leve e ergonômico retornado por consultas SQL.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 * @implements ArrayAccess<int|string, mixed>
 */
final readonly class QueryResult implements Countable, IteratorAggregate, ArrayAccess, JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    public array $row;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows = [],
        public int $count = 0,
        public float $elapsedTime = 0.0
    ) {
        $this->row = $this->rows[0] ?? [];
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return max(0, $this->count);
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        yield from $this->rows;
    }

    /**
     * @return array<int, mixed>
     */
    public function column(string $columnName): array
    {
        return array_column($this->rows, $columnName);
    }

    /**
     * @param (callable(array<string, mixed>): bool)|null $predicate
     * @param array<string, mixed>|null $default
     * @return array<string, mixed>|null
     */
    public function firstOrDefault(?callable $predicate = null, ?array $default = null): ?array
    {
        if (empty($this->rows)) {
            return $default;
        }

        if ($predicate === null) {
            return $this->rows[0] ?? $default;
        }

        foreach ($this->rows as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }

        return $default;
    }

    /**
     * Converte as linhas do resultado diretamente em uma instância de LinqPHP.
     */
    public function linq(): mixed
    {
        if (!class_exists("HeliomarPM\\LinqPHP\\LinqPHP")) {
            throw new RuntimeException("O pacote 'heliomarpm/linq-php' não está instalado. Instale com: composer require heliomarpm/linq-php");
        }

        return \HeliomarPM\LinqPHP\LinqPHP::from($this->rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            "count" => $this->count,
            "elapsedTime" => $this->elapsedTime,
            "row" => $this->row,
            "rows" => $this->rows,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->rows[$offset]) || isset($this->row[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (is_int($offset)) {
            return $this->rows[$offset] ?? null;
        }

        return $this->row[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException("QueryResult é imutável.");
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException("QueryResult é imutável.");
    }
}
