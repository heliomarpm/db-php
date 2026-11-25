<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use Generator;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;

final class PdoDriver extends AbstractDriver
{
    private ?PDO $pdo = null;
    private bool $closed = false;

    /**
     * @var array<string, PDOStatement>
     */
    private array $statementCache = [];

    public function __construct(
        Config $config,
        ?PDO $existingPdo = null
    ) {
        parent::__construct($config);

        if ($existingPdo !== null) {
            $this->pdo = $existingPdo;
            return;
        }

        if (!$this->config->lazy) {
            $this->connect();
        }
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    public function cursor(string $sql, array $params = []): Generator
    {
        $this->assertConnected();
        $startNs = hrtime(true);

        [$resolvedSql, $resolvedParams] = self::resolveNamedParameters($sql, $params);
        $yielded = 0;

        try {
            $stmt = $this->prepareStatement($resolvedSql, $resolvedParams, cacheable: false);
            $stmt->execute();

            try {
                while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    /** @var array<string, mixed> $row */
                    $yielded++;
                    yield $row;
                }
            } finally {
                $stmt->closeCursor();
                $elapsedTime = (hrtime(true) - $startNs) / 1e9;
                $this->logQuery($sql, $params, $elapsedTime, "cursor", $yielded);
            }
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function clearStatementCache(): void
    {
        $this->statementCache = [];
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0 || ($this->pdo !== null && $this->pdo->inTransaction());
    }

    public function lastInsertId(): int|string
    {
        $this->assertConnected();
        $id = $this->pdo->lastInsertId();
        if ($id === false) {
            return 0;
        }
        return is_numeric($id) ? (int)$id : $id;
    }

    public function escape(string $value): string
    {
        $this->assertConnected();
        $quoted = $this->pdo->quote($value);
        if ($quoted === false) {
            return addslashes($value);
        }

        if (str_starts_with($quoted, "'") && str_ends_with($quoted, "'")) {
            return substr($quoted, 1, -1);
        }

        return $quoted;
    }

    public function close(): void
    {
        $this->closed = true;
        $pdo = $this->pdo;
        if ($pdo !== null) {
            $this->clearStatementCache();
            if ($this->transactionLevel > 0 || $pdo->inTransaction()) {
                $this->doRollback();
                $this->transactionLevel = 0;
            }
            $this->pdo = null;
        }
    }

    public function getPdo(): PDO
    {
        $this->assertConnected();
        return $this->pdo;
    }

    /**
     * @param array<int|string, mixed> $resolvedParams
     * @param array<int|string, mixed> $originalParams
     * @return array<int, array<string, mixed>>
     */
    protected function doQuery(
        string $resolvedSql,
        array $resolvedParams,
        string $originalSql,
        array $originalParams
    ): array {
        try {
            $stmt = $this->prepareStatement($resolvedSql, $resolvedParams);
            $stmt->execute();

            /** @var array<int, array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($this->config->statementCacheSize > 0) {
                $stmt->closeCursor();
            }

            return $rows;
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), $originalSql, $originalParams, $e->getCode(), $e);
        }
    }

    /**
     * @param array<int|string, mixed> $resolvedParams
     * @param array<int|string, mixed> $originalParams
     */
    protected function doExecute(
        string $resolvedSql,
        array $resolvedParams,
        string $originalSql,
        array $originalParams
    ): int {
        try {
            $stmt = $this->prepareStatement($resolvedSql, $resolvedParams);
            $stmt->execute();
            $rowCount = $stmt->rowCount();

            if ($this->config->statementCacheSize > 0) {
                $stmt->closeCursor();
            }

            return $rowCount;
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), $originalSql, $originalParams, $e->getCode(), $e);
        }
    }

    /**
     * @phpstan-assert !null $this->pdo
     */
    protected function assertConnected(): void
    {
        if ($this->pdo === null && $this->config->lazy && !$this->closed) {
            $this->connect();
        }

        if ($this->pdo === null) {
            throw new ConnectionException("A conexão com o banco de dados está encerrada.");
        }
    }

    protected function doBeginTransaction(): void
    {
        $this->assertConnected();
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    protected function doCommit(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    protected function doRollback(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    protected function executeTransactionSql(string $sql): void
    {
        $this->assertConnected();
        $this->pdo->exec($sql);
    }

    private function connect(): void
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        foreach ($this->config->options as $key => $value) {
            if (is_int($key)) {
                $options[$key] = $value;
            }
        }

        try {
            $driverLower = strtolower($this->config->driver);
            $hasCustomDsn = $this->config->dsn !== null && $this->config->dsn !== "";

            $isSqlite = !$hasCustomDsn && (
                $driverLower === "sqlite"
                || $this->config->database === ":memory:"
                || str_ends_with(strtolower($this->config->database), ".sqlite")
                || str_ends_with(strtolower($this->config->database), ".sqlite3")
                || str_ends_with(strtolower($this->config->database), ".db")
            );

            if ($hasCustomDsn) {
                $this->pdo = new PDO((string)$this->config->dsn, $this->config->username, $this->config->password, $options);
            } elseif ($isSqlite) {
                $dsn = "sqlite:{$this->config->database}";
                $this->pdo = new PDO($dsn, null, null, $options);
            } elseif (in_array($driverLower, ["pgsql", "postgres"], true)) {
                $dsn = "pgsql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}";
                $this->pdo = new PDO($dsn, $this->config->username, $this->config->password, $options);

                if (preg_match("/^[a-zA-Z0-9_\\-]+$/", $this->config->charset) === 1) {
                    $this->pdo->exec("SET NAMES '{$this->config->charset}'");
                }
            } else {
                $dsn = "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database};charset={$this->config->charset}";
                $this->pdo = new PDO($dsn, $this->config->username, $this->config->password, $options);

                if ($this->isCompatibleCollation($this->config->charset, $this->config->collation)) {
                    $this->pdo->exec("SET NAMES '{$this->config->charset}' COLLATE '{$this->config->collation}'");
                }
            }
        } catch (PDOException $e) {
            $code = is_numeric($e->getCode()) ? (int)$e->getCode() : 0;
            throw new ConnectionException("Falha ao conectar via PDO: " . $e->getMessage(), $code, $e);
        }
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function prepareStatement(string $sql, array $params, bool $cacheable = true): PDOStatement
    {
        $this->assertConnected();
        $stmt = $this->getPreparedStatement($sql, $cacheable);

        foreach ($params as $key => $value) {
            if (is_object($value)) {
                $value = self::normalizeParamValue($value);
            }
            $paramType = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $paramName = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ":") ? (string)$key : ":{$key}");
            $stmt->bindValue($paramName, $value, $paramType);
        }

        return $stmt;
    }

    private function getPreparedStatement(string $sql, bool $cacheable = true): PDOStatement
    {
        $this->assertConnected();

        if ($cacheable && $this->config->statementCacheSize > 0) {
            if (isset($this->statementCache[$sql])) {
                $stmt = $this->statementCache[$sql];
                unset($this->statementCache[$sql]);
                $this->statementCache[$sql] = $stmt;
                return $stmt;
            }

            $stmt = $this->pdo->prepare($sql);
            if (count($this->statementCache) >= $this->config->statementCacheSize) {
                array_shift($this->statementCache);
            }
            $this->statementCache[$sql] = $stmt;

            return $stmt;
        }

        return $this->pdo->prepare($sql);
    }
}
