<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use HeliomarPM\DbPHP\Concerns\PaginatesQueries;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use HeliomarPM\DbPHP\Results\QueryResult;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class PdoDriver implements DriverInterface
{
    use PaginatesQueries;

    private ?PDO $pdo;
    private int $affectedRows = 0;

    public function __construct(
        private readonly Config $config,
        ?PDO $existingPdo = null
    ) {
        if ($existingPdo !== null) {
            $this->pdo = $existingPdo;
            return;
        }

        $this->connect();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function query(string $sql, array $params = []): QueryResult
    {
        $this->assertConnected();
        $startTime = microtime(true);

        try {
            $stmt = $this->prepareStatement($sql, $params);
            $stmt->execute();

            /** @var array<int, array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $elapsedTime = microtime(true) - $startTime;

            $this->affectedRows = $stmt->rowCount();

            return new QueryResult(
                rows: $rows,
                count: count($rows),
                elapsedTime: $elapsedTime
            );
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->assertConnected();

        try {
            $stmt = $this->prepareStatement($sql, $params);
            $stmt->execute();

            $this->affectedRows = $stmt->rowCount();

            return $this->affectedRows;
        } catch (PDOException $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function beginTransaction(): void
    {
        $this->assertConnected();
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollback(): void
    {
        $this->assertConnected();
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo !== null && $this->pdo->inTransaction();
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

    public function lastInsertId(): int|string
    {
        $this->assertConnected();
        $id = $this->pdo->lastInsertId();
        if ($id === false) {
            return 0;
        }
        return is_numeric($id) ? (int)$id : $id;
    }

    public function countAffected(): int
    {
        return $this->affectedRows;
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
        if ($this->pdo !== null) {
            if ($this->pdo->inTransaction()) {
                $this->rollback();
            }
            $this->pdo = null;
        }
    }

    public function getPdo(): PDO
    {
        $this->assertConnected();
        return $this->pdo;
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
            $options[(int)$key] = $value;
        }

        try {
            $isSqlite = strtolower($this->config->driver) === "sqlite"
                || $this->config->database === ":memory:"
                || str_ends_with(strtolower($this->config->database), ".sqlite")
                || str_ends_with(strtolower($this->config->database), ".sqlite3")
                || str_ends_with(strtolower($this->config->database), ".db");

            if ($isSqlite) {
                $dsn = "sqlite:{$this->config->database}";
                $this->pdo = new PDO($dsn, null, null, $options);
            } else {
                $dsn = "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database};charset={$this->config->charset}";
                $this->pdo = new PDO($dsn, $this->config->username, $this->config->password, $options);
            }
        } catch (PDOException $e) {
            $code = is_numeric($e->getCode()) ? (int)$e->getCode() : 0;
            throw new ConnectionException("Falha ao conectar via PDO: " . $e->getMessage(), $code, $e);
        }
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function prepareStatement(string $sql, array $params): PDOStatement
    {
        $this->assertConnected();
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
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

    /**
     * @phpstan-assert !null $this->pdo
     */
    private function assertConnected(): void
    {
        if ($this->pdo === null) {
            throw new ConnectionException("A conexão com o banco de dados está encerrada.");
        }
    }
}
