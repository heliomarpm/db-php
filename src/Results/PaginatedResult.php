<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Results;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use Traversable;

/**
 * Objeto imutável contendo os registros da página atual e metadados de paginação.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final readonly class PaginatedResult implements Countable, IteratorAggregate, JsonSerializable
{
    public int $lastPage;
    public ?int $from;
    public ?int $to;
    public bool $hasMorePages;
    public bool $hasPreviousPages;

    /**
     * @param array<int, array<string, mixed>> $items Registros da página atual
     * @param int $total Total global de registros correspondentes
     * @param int $perPage Quantidade de registros por página
     * @param int $currentPage Página atual (1-indexed)
     * @param float $elapsedTime Tempo total de execução somado (contagem + consulta)
     */
    public function __construct(
        public array $items = [],
        public int $total = 0,
        public int $perPage = 15,
        public int $currentPage = 1,
        public float $elapsedTime = 0.0
    ) {
        $validTotal = max(0, $this->total);
        $validPerPage = max(1, $this->perPage);
        $validPage = max(1, $this->currentPage);

        $this->lastPage = max(1, (int)ceil($validTotal / $validPerPage));

        $count = count($this->items);
        if ($count > 0 && $validPage <= $this->lastPage) {
            $fromIndex = (($validPage - 1) * $validPerPage) + 1;
            $this->from = $fromIndex;
            $this->to = $fromIndex + $count - 1;
        } else {
            $this->from = null;
            $this->to = null;
        }

        $this->hasMorePages = $validPage < $this->lastPage;
        $this->hasPreviousPages = $validPage > 1;
    }

    /**
     * Retorna a quantidade de itens presentes na página atual.
     *
     * @return int<0, max>
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Verifica se a página atual está vazia.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Verifica se a página atual possui itens.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    /**
     * Extrai os valores de uma coluna específica da página atual.
     *
     * @return list<mixed>
     */
    public function column(string $name): array
    {
        return array_column($this->items, $name);
    }

    /**
     * Retorna o primeiro registro que satisfaz a condição ou o valor padrão.
     *
     * @param (callable(array<string, mixed>): bool)|null $predicate
     * @param mixed $default
     * @return mixed
     */
    public function firstOrDefault(?callable $predicate = null, mixed $default = null): mixed
    {
        if (empty($this->items)) {
            return $default;
        }

        if ($predicate === null) {
            return $this->items[0];
        }

        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * Transfere os itens da página atual para uma coleção LinqPHP fluente.
     */
    public function linq(): mixed
    {
        if (class_exists(\HeliomarPM\LinqPHP\LinqPHP::class)) {
            return \HeliomarPM\LinqPHP\LinqPHP::from($this->items);
        }

        throw new RuntimeException("O pacote heliomarpm/linq-php não está instalado. Execute: composer require heliomarpm/linq-php");
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Serialização nativa para retorno padronizado em JSON / APIs REST.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            "data" => $this->items,
            "pagination" => [
                "total" => $this->total,
                "per_page" => $this->perPage,
                "current_page" => $this->currentPage,
                "last_page" => $this->lastPage,
                "from" => $this->from,
                "to" => $this->to,
                "has_more" => $this->hasMorePages,
                "has_previous" => $this->hasPreviousPages,
            ],
            "elapsed_time" => $this->elapsedTime,
        ];
    }
}
