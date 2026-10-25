<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Concerns;

use HeliomarPM\DbPHP\Results\PaginatedResult;
use HeliomarPM\DbPHP\Results\QueryResult;

trait PaginatesQueries
{
    /**
     * Executa uma consulta de leitura.
     *
     * @param array<int|string, mixed> $params
     */
    abstract public function query(string $sql, array $params = []): QueryResult;

    /**
     * Executa consulta paginada retornando registros e metadados de paginação.
     *
     * @param array<int|string, mixed> $params
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): PaginatedResult
    {
        $validPage = max(1, $page);
        $validPerPage = max(1, $perPage);
        $offset = ($validPage - 1) * $validPerPage;
        $startTime = microtime(true);

        $cleanSql = rtrim(trim($sql), ";");
        $countSql = "SELECT COUNT(*) AS total_count FROM ({$cleanSql}) AS count_subquery";

        $countResult = $this->query($countSql, $params);
        $total = (int) ($countResult->row["total_count"] ?? 0);

        if ($total === 0) {
            return new PaginatedResult(
                items: [],
                total: 0,
                perPage: $validPerPage,
                currentPage: $validPage,
                elapsedTime: microtime(true) - $startTime
            );
        }

        if (empty($params) || array_is_list($params)) {
            $paginatedSql = "{$cleanSql} LIMIT ? OFFSET ?";
            $paginatedParams = [...$params, $validPerPage, $offset];
        } else {
            $paginatedSql = "{$cleanSql} LIMIT :__db_limit__ OFFSET :__db_offset__";
            $paginatedParams = $params;
            $paginatedParams["__db_limit__"] = $validPerPage;
            $paginatedParams["__db_offset__"] = $offset;
        }

        $queryResult = $this->query($paginatedSql, $paginatedParams);
        $elapsedTime = microtime(true) - $startTime;

        return new PaginatedResult(
            items: $queryResult->rows,
            total: $total,
            perPage: $validPerPage,
            currentPage: $validPage,
            elapsedTime: $elapsedTime
        );
    }
}
