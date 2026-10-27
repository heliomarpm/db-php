<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Concerns;

use RuntimeException;

/**
 * Trait com helpers de manipulação de resultados compartilhados entre QueryResult e PaginatedResult.
 *
 * Provê: column(), firstOrDefault() e linq().
 *
 * @internal Para uso exclusivo em QueryResult e PaginatedResult.
 */
trait HasResultHelpers
{
    /**
     * Retorna o array interno de linhas/itens utilizado pelos helpers deste trait.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function getResultRows(): array;
    /**
     * Extrai os valores de uma coluna específica de todas as linhas do resultado.
     *
     * @return list<mixed>
     */
    public function column(string $columnName): array
    {
        return array_column($this->getResultRows(), $columnName);
    }

    /**
     * Retorna o primeiro registro que satisfaz o predicado ou o valor padrão.
     *
     * @param (callable(array<string, mixed>): bool)|null $predicate
     * @param mixed $default
     * @return mixed
     */
    public function firstOrDefault(?callable $predicate = null, mixed $default = null): mixed
    {
        $rows = $this->getResultRows();

        if (empty($rows)) {
            return $default;
        }

        if ($predicate === null) {
            return $rows[0] ?? $default;
        }

        foreach ($rows as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }

        return $default;
    }

    /**
     * Converte as linhas do resultado em uma instância fluente de LinqPHP.
     *
     * Requer o pacote opcional: composer require heliomarpm/linq-php
     */
    public function linq(): mixed
    {
        if (! class_exists(\HeliomarPM\LinqPHP\LinqPHP::class)) {
            throw new RuntimeException("O pacote 'heliomarpm/linq-php' não está instalado. Execute: composer require heliomarpm/linq-php");
        }

        return \HeliomarPM\LinqPHP\LinqPHP::from($this->getResultRows());
    }
}
