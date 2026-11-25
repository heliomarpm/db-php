<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Results;

use ArrayIterator;
use Countable;
use HeliomarPM\DbPHP\Concerns\HasResultHelpers;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Objeto imutável para paginação simples em única consulta (sem COUNT(*)).
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final readonly class SimplePaginatedResult implements Countable, IteratorAggregate, JsonSerializable
{
    use HasResultHelpers;

    public ?int $from;
    public ?int $to;
    public bool $hasPreviousPages;

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public array $items = [],
        public int $perPage = 15,
        public int $currentPage = 1,
        public bool $hasMorePages = false,
        public float $elapsedTime = 0.0
    ) {
        $validPerPage = max(1, $this->perPage);
        $validPage = max(1, $this->currentPage);

        $count = count($this->items);
        if ($count > 0) {
            $fromIndex = (($validPage - 1) * $validPerPage) + 1;
            $this->from = $fromIndex;
            $this->to = $fromIndex + $count - 1;
        } else {
            $this->from = null;
            $this->to = null;
        }

        $this->hasPreviousPages = $validPage > 1;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            "data" => $this->items,
            "pagination" => [
                "per_page" => $this->perPage,
                "current_page" => $this->currentPage,
                "from" => $this->from,
                "to" => $this->to,
                "has_more" => $this->hasMorePages,
                "has_previous" => $this->hasPreviousPages,
            ],
            "elapsed_time" => $this->elapsedTime,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getResultRows(): array
    {
        return $this->items;
    }
}
