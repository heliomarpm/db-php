<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Results;

use ArrayAccess;
use Countable;
use HeliomarPM\DbPHP\Concerns\HasResultHelpers;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Traversable;

/**
 * Objeto de resultado leve e ergonômico retornado por consultas SQL.
 *
 * O campo `count` é calculado automaticamente a partir de `count($rows)`.
 * O parâmetro `$count` permanece aceito no construtor por retrocompatibilidade,
 * mas é ignorado em favor do cálculo automático.
 *
 * ### Estrutura JSON (`jsonSerialize`)
 * ```json
 * {
 *   "count": 5,
 *   "elapsed_time": 0.0012,
 *   "row": { "id": 1, "nome": "..." },
 *   "rows": [...]
 * }
 * ```
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 * @implements ArrayAccess<int|string, mixed>
 */
final readonly class QueryResult implements Countable, IteratorAggregate, ArrayAccess, JsonSerializable
{
    use HasResultHelpers;

    /**
     * Primeira linha do resultado (atalho para `$rows[0]`).
     *
     * @var array<string, mixed>
     */
    public array $row;

    /**
     * Quantidade real de linhas no resultado (calculada automaticamente).
     */
    public int $count;

    /**
     * @param array<int, array<string, mixed>> $rows       Linhas retornadas pela consulta
     * @param int                              $count      Ignorado; mantido por retrocompatibilidade. Use `count($rows)`.
     * @param float                            $elapsedTime Tempo de execução em segundos
     *
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(
        public array $rows = [],
        int $count = 0,
        public float $elapsedTime = 0.0
    ) {
        $this->count = count($this->rows);
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
     * Serialização padronizada para JSON / APIs REST.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'count'        => $this->count,
            'elapsed_time' => $this->elapsedTime,
            'row'          => $this->row,
            'rows'         => $this->rows,
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
        throw new RuntimeException('QueryResult é imutável.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('QueryResult é imutável.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getResultRows(): array
    {
        return $this->rows;
    }
}
