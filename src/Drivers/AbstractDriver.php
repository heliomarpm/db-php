<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use BackedEnum;
use DateTimeInterface;
use HeliomarPM\DbPHP\Concerns\PaginatesQueries;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use HeliomarPM\DbPHP\Results\QueryResult;
use HeliomarPM\DbPHP\Support\QueryLog;
use Stringable;
use Throwable;

abstract class AbstractDriver implements DriverInterface
{
    use PaginatesQueries;

    private const MAX_SQL_TEMPLATE_CACHE = 256;

    protected int $affectedRows = 0;
    protected int $transactionLevel = 0;
    protected ?string $connectionName = null;

    /**
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private static array $namedSqlCache = [];

    public function __construct(
        protected readonly Config $config
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param array<int|string, mixed> $resolvedParams
     * @param array<int|string, mixed> $originalParams
     * @return array<int, array<string, mixed>>
     */
    abstract protected function doQuery(
        string $resolvedSql,
        array $resolvedParams,
        string $originalSql,
        array $originalParams
    ): array;

    /**
     * @param array<int|string, mixed> $resolvedParams
     * @param array<int|string, mixed> $originalParams
     */
    abstract protected function doExecute(
        string $resolvedSql,
        array $resolvedParams,
        string $originalSql,
        array $originalParams
    ): int;

    abstract protected function assertConnected(): void;

    abstract protected function doBeginTransaction(): void;

    abstract protected function doCommit(): void;

    abstract protected function doRollback(): void;

    abstract protected function executeTransactionSql(string $sql): void;

    public static function clearNamedSqlCache(): void
    {
        self::$namedSqlCache = [];
    }

    /**
     * Converte parâmetros nomeados em posicionais (?) ignorando literais de texto,
     * identificadores, comentários SQL e operadores especiais (::, :=).
     *
     * @param array<int|string, mixed> $params
     * @return array{0: string, 1: list<mixed>}
     * @throws QueryException
     */
    public static function resolveNamedParameters(string $sql, array $params): array
    {
        if (empty($params) || array_is_list($params)) {
            return [$sql, $params];
        }

        if (!str_contains($sql, ":")) {
            return [$sql, []];
        }

        if (isset(self::$namedSqlCache[$sql])) {
            [$translatedSql, $keyOrder] = self::$namedSqlCache[$sql];
        } else {
            $pattern = '/(
                \'(?:[^\'\\\\]|\\\\.|\'\')*\'
                |
                "(?:[^"\\\\]|\\\\.|"")*"
                |
                `(?:[^`\\\\]|\\\\.|``)*`
                |
                (?:--|\#)[^\r\n]*
                |
                \/\*[\s\S]*?\*\/
            )
            |
            (::|:=)
            |
            (?<!:):([a-zA-Z_][a-zA-Z0-9_]*)
            /xs';

            $keyOrder = [];
            $translatedSql = preg_replace_callback(
                $pattern,
                static function (array $matches) use (&$keyOrder): string {
                    if (!empty($matches[1]) || !empty($matches[2])) {
                        return $matches[0];
                    }

                    $keyOrder[] = $matches[3];
                    return "?";
                },
                $sql
            );

            if ($translatedSql === null) {
                throw new QueryException("Erro ao processar parâmetros na consulta SQL: " . preg_last_error_msg(), $sql, $params);
            }

            if (count(self::$namedSqlCache) >= self::MAX_SQL_TEMPLATE_CACHE) {
                array_shift(self::$namedSqlCache);
            }
            self::$namedSqlCache[$sql] = [$translatedSql, $keyOrder];
        }

        $orderedParams = [];
        foreach ($keyOrder as $key) {
            if (array_key_exists($key, $params)) {
                $val = $params[$key];
            } elseif (array_key_exists(":" . $key, $params)) {
                $val = $params[":" . $key];
            } else {
                throw new QueryException("Parâmetro nomeado ':{$key}' não foi informado.", $sql, $params);
            }

            if (is_array($val)) {
                return self::expandNamedArrayParameters($sql, $params);
            }

            $orderedParams[] = $val;
        }

        return [$translatedSql, $orderedParams];
    }

    public function row(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);

        return $result->rows[0] ?? null;
    }

    public function scalar(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = $this->row($sql, $params);
        if ($row === null || $row === []) {
            return $default;
        }

        $firstKey = array_key_first($row);

        return $row[$firstKey];
    }

    public function column(string $sql, array $params = [], int|string $column = 0): array
    {
        $result = $this->query($sql, $params);
        if ($result->rows === []) {
            return [];
        }

        if (is_string($column)) {
            return array_column($result->rows, $column);
        }

        $values = [];
        foreach ($result->rows as $row) {
            $indexed = array_values($row);
            if (array_key_exists($column, $indexed)) {
                $values[] = $indexed[$column];
            }
        }

        return $values;
    }

    public function query(string $sql, array $params = []): QueryResult
    {
        $this->assertConnected();
        $startNs = hrtime(true);

        [$resolvedSql, $resolvedParams] = self::resolveNamedParameters($sql, $params);
        $rows = $this->doQuery($resolvedSql, $resolvedParams, $sql, $params);

        $elapsedTime = (hrtime(true) - $startNs) / 1e9;
        $this->logQuery($sql, $params, $elapsedTime, "query", count($rows));

        return new QueryResult(
            rows: $rows,
            elapsedTime: $elapsedTime
        );
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->assertConnected();
        $startNs = hrtime(true);

        [$resolvedSql, $resolvedParams] = self::resolveNamedParameters($sql, $params);
        $this->affectedRows = $this->doExecute($resolvedSql, $resolvedParams, $sql, $params);

        $elapsedTime = (hrtime(true) - $startNs) / 1e9;
        $this->logQuery($sql, $params, $elapsedTime, "execute", $this->affectedRows);

        return $this->affectedRows;
    }

    public function setConnectionName(?string $name): void
    {
        $this->connectionName = $name;
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function clearStatementCache(): void {}

    public function countAffected(): int
    {
        return $this->affectedRows;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function beginTransaction(): void
    {
        $this->assertConnected();

        if ($this->transactionLevel === 0) {
            $this->doBeginTransaction();
            $this->transactionLevel = 1;
            return;
        }

        $nextLevel = $this->transactionLevel + 1;
        $this->executeTransactionSql("SAVEPOINT dbphp_sp_{$nextLevel}");
        $this->transactionLevel = $nextLevel;
    }

    public function commit(): void
    {
        $this->assertConnected();

        if ($this->transactionLevel === 0) {
            return;
        }

        if ($this->transactionLevel === 1) {
            try {
                $this->doCommit();
            } finally {
                $this->transactionLevel = 0;
            }
            return;
        }

        $this->executeTransactionSql("RELEASE SAVEPOINT dbphp_sp_{$this->transactionLevel}");
        $this->transactionLevel--;
    }

    public function rollback(): void
    {
        $this->assertConnected();

        if ($this->transactionLevel === 0) {
            return;
        }

        if ($this->transactionLevel === 1) {
            try {
                $this->doRollback();
            } finally {
                $this->transactionLevel = 0;
            }
            return;
        }

        $this->executeTransactionSql("ROLLBACK TO SAVEPOINT dbphp_sp_{$this->transactionLevel}");
        $this->executeTransactionSql("RELEASE SAVEPOINT dbphp_sp_{$this->transactionLevel}");
        $this->transactionLevel--;
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function isDebug(): bool
    {
        return $this->config->debug || QueryLog::isEnabled();
    }

    protected static function normalizeParamValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format("Y-m-d H:i:s"),
            $value instanceof Stringable => (string)$value,
            default => $value,
        };
    }

    protected function isCompatibleCollation(string $charset, string $collation): bool
    {
        return $collation !== ""
            && preg_match("/^[a-zA-Z0-9_]+$/", $charset) === 1
            && preg_match("/^[a-zA-Z0-9_]+$/", $collation) === 1
            && str_starts_with(strtolower($collation), strtolower($charset) . "_");
    }

    /**
     * Registra a consulta no coletor de telemetria se o modo debug estiver ativo.
     *
     * @param array<int|string, mixed> $params
     */
    protected function logQuery(
        string $sql,
        array $params,
        float $elapsedTime,
        string $type = "query",
        int $affectedRows = 0
    ): void {
        if (!$this->isDebug()) {
            return;
        }

        QueryLog::record(
            sql: $sql,
            params: $params,
            elapsedTime: $elapsedTime,
            type: $type,
            driver: $this->config->driver,
            affectedRows: $affectedRows,
            connection: $this->connectionName
        );
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array{0: string, 1: list<mixed>}
     * @throws QueryException
     */
    private static function expandNamedArrayParameters(string $sql, array $params): array
    {
        $pattern = '/(
            \'(?:[^\'\\\\]|\\\\.|\'\')*\'
            |
            "(?:[^"\\\\]|\\\\.|"")*"
            |
            `(?:[^`\\\\]|\\\\.|``)*`
            |
            (?:--|\#)[^\r\n]*
            |
            \/\*[\s\S]*?\*\/
        )
        |
        (::|:=)
        |
        (?<!:):([a-zA-Z_][a-zA-Z0-9_]*)
        /xs';

        $orderedParams = [];
        $translatedSql = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($sql, $params, &$orderedParams): string {
                if (!empty($matches[1]) || !empty($matches[2])) {
                    return $matches[0];
                }

                $key = $matches[3];
                if (array_key_exists($key, $params)) {
                    $val = $params[$key];
                } elseif (array_key_exists(":" . $key, $params)) {
                    $val = $params[":" . $key];
                } else {
                    throw new QueryException("Parâmetro nomeado ':{$key}' não foi informado.", $sql, $params);
                }

                if (is_array($val)) {
                    if ($val === []) {
                        throw new QueryException("O parâmetro de lista ':{$key}' não pode ser um array vazio.", $sql, $params);
                    }

                    foreach ($val as $item) {
                        $orderedParams[] = $item;
                    }

                    return implode(", ", array_fill(0, count($val), "?"));
                }

                $orderedParams[] = $val;
                return "?";
            },
            $sql
        );

        if ($translatedSql === null) {
            throw new QueryException("Erro ao processar parâmetros na consulta SQL: " . preg_last_error_msg(), $sql, $params);
        }

        return [$translatedSql, $orderedParams];
    }
}
