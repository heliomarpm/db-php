<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Concerns;

use HeliomarPM\DbPHP\Results\PaginatedResult;
use HeliomarPM\DbPHP\Results\QueryResult;
use HeliomarPM\DbPHP\Results\SimplePaginatedResult;

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
        $startNs = hrtime(true);

        $cleanSql = rtrim(trim($sql), ";");
        $countInnerSql = $this->removeTrailingOrderBy($cleanSql);
        $countSql = "SELECT COUNT(*) AS total_count FROM ({$countInnerSql}) AS count_subquery";

        $countResult = $this->query($countSql, $params);
        $total = (int)($countResult->row["total_count"] ?? 0);

        if ($total === 0 || $offset >= $total) {
            return new PaginatedResult(
                items: [],
                total: $total,
                perPage: $validPerPage,
                currentPage: $validPage,
                elapsedTime: (hrtime(true) - $startNs) / 1e9
            );
        }

        $baseSql = $this->hasTopLevelLimit($cleanSql)
            ? "SELECT * FROM ({$cleanSql}) AS paginated_subquery"
            : $cleanSql;

        if (empty($params) || array_is_list($params)) {
            $paginatedSql = "{$baseSql} LIMIT ? OFFSET ?";
            $paginatedParams = [...$params, $validPerPage, $offset];
        } else {
            $paginatedSql = "{$baseSql} LIMIT :__db_limit__ OFFSET :__db_offset__";
            $paginatedParams = $params;
            $paginatedParams["__db_limit__"] = $validPerPage;
            $paginatedParams["__db_offset__"] = $offset;
        }

        $queryResult = $this->query($paginatedSql, $paginatedParams);
        $elapsedTime = (hrtime(true) - $startNs) / 1e9;

        return new PaginatedResult(
            items: $queryResult->rows,
            total: $total,
            perPage: $validPerPage,
            currentPage: $validPage,
            elapsedTime: $elapsedTime
        );
    }

    /**
     * Executa paginação simples em única consulta (LIMIT perPage + 1), sem COUNT(*).
     *
     * @param array<int|string, mixed> $params
     */
    public function simplePaginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): SimplePaginatedResult
    {
        $validPage = max(1, $page);
        $validPerPage = max(1, $perPage);
        $offset = ($validPage - 1) * $validPerPage;
        $startNs = hrtime(true);

        $cleanSql = rtrim(trim($sql), ";");
        $baseSql = $this->hasTopLevelLimit($cleanSql)
            ? "SELECT * FROM ({$cleanSql}) AS paginated_subquery"
            : $cleanSql;

        $fetchLimit = $validPerPage + 1;

        if (empty($params) || array_is_list($params)) {
            $paginatedSql = "{$baseSql} LIMIT ? OFFSET ?";
            $paginatedParams = [...$params, $fetchLimit, $offset];
        } else {
            $paginatedSql = "{$baseSql} LIMIT :__db_limit__ OFFSET :__db_offset__";
            $paginatedParams = $params;
            $paginatedParams["__db_limit__"] = $fetchLimit;
            $paginatedParams["__db_offset__"] = $offset;
        }

        $queryResult = $this->query($paginatedSql, $paginatedParams);
        $elapsedTime = (hrtime(true) - $startNs) / 1e9;

        $rows = $queryResult->rows;
        $hasMore = count($rows) > $validPerPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $validPerPage);
        }

        return new SimplePaginatedResult(
            items: $rows,
            perPage: $validPerPage,
            currentPage: $validPage,
            hasMorePages: $hasMore,
            elapsedTime: $elapsedTime
        );
    }

    /**
     * Remove a cláusula ORDER BY externa (nível 0) de uma instrução SQL.
     * Preserva ORDER BY presentes dentro de subqueries, window functions, literais ou comentários,
     * bem como cláusulas que contenham placeholders de bind ou LIMIT externo.
     */
    public function removeTrailingOrderBy(string $sql): string
    {
        $len = strlen($sql);
        $depth = 0;
        $inQuote = false;
        $quoteChar = "";
        $lastOrderByPos = null;

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($inQuote) {
                if ($char === $quoteChar) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quoteChar) {
                        $i++;
                    } else {
                        $inQuote = false;
                    }
                } elseif ($char === "\\") {
                    $i++;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === "`") {
                $inQuote = true;
                $quoteChar = $char;
                continue;
            }

            if ($char === "#" || ($char === "-" && $i + 1 < $len && $sql[$i + 1] === "-")) {
                $newlinePos = strpos($sql, "\n", $i);
                $i = $newlinePos === false ? $len - 1 : $newlinePos;
                continue;
            }

            if ($char === "/" && $i + 1 < $len && $sql[$i + 1] === "*") {
                $endCommentPos = strpos($sql, "*/", $i + 2);
                $i = $endCommentPos === false ? $len - 1 : $endCommentPos + 1;
                continue;
            }

            if ($char === "(") {
                $depth++;
                continue;
            }

            if ($char === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }

            if (
                $depth === 0
                && ($char === "o" || $char === "O")
                && ($i === 0 || ctype_space($sql[$i - 1]) || $sql[$i - 1] === ")")
                && preg_match('/\GORDER\s+BY\b/i', $sql, $matches, 0, $i) === 1
            ) {
                $lastOrderByPos = $i;
            }
        }

        if ($lastOrderByPos !== null) {
            $trailing = substr($sql, $lastOrderByPos);
            if ($this->containsPlaceholderOrLimitOutsideLiterals($trailing)) {
                return $sql;
            }

            return rtrim(substr($sql, 0, $lastOrderByPos));
        }

        return $sql;
    }

    private function hasTopLevelLimit(string $sql): bool
    {
        $len = strlen($sql);
        $depth = 0;
        $inQuote = false;
        $quoteChar = "";

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($inQuote) {
                if ($char === $quoteChar) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quoteChar) {
                        $i++;
                    } else {
                        $inQuote = false;
                    }
                } elseif ($char === "\\") {
                    $i++;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === "`") {
                $inQuote = true;
                $quoteChar = $char;
                continue;
            }

            if ($char === "#" || ($char === "-" && $i + 1 < $len && $sql[$i + 1] === "-")) {
                $newlinePos = strpos($sql, "\n", $i);
                $i = $newlinePos === false ? $len - 1 : $newlinePos;
                continue;
            }

            if ($char === "/" && $i + 1 < $len && $sql[$i + 1] === "*") {
                $endCommentPos = strpos($sql, "*/", $i + 2);
                $i = $endCommentPos === false ? $len - 1 : $endCommentPos + 1;
                continue;
            }

            if ($char === "(") {
                $depth++;
                continue;
            }

            if ($char === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }

            if (
                $depth === 0
                && ($char === "l" || $char === "L")
                && ($i === 0 || ctype_space($sql[$i - 1]) || $sql[$i - 1] === ")")
                && preg_match('/\GLIMIT\b/i', $sql, $matches, 0, $i) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function containsPlaceholderOrLimitOutsideLiterals(string $sqlFragment): bool
    {
        $stripped = (string)preg_replace(
            "/('(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.|\"\")*\"|`(?:[^`\\\\]|\\\\.|``)*`|(?:--|#)[^\\r\\n]*|\\/\\*[\\s\\S]*?\\*\\/)/",
            " ",
            $sqlFragment
        );

        return str_contains($stripped, "?")
            || preg_match("/(?<!:):[a-zA-Z_][a-zA-Z0-9_]*/", $stripped) === 1
            || preg_match('/\bLIMIT\b/i', $stripped) === 1;
    }
}
