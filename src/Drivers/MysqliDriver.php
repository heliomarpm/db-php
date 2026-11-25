<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use Exception;
use Generator;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;

final class MysqliDriver extends AbstractDriver
{
    private ?mysqli $conn = null;
    private bool $closed = false;

    /**
     * @var array<string, mysqli_stmt>
     */
    private array $statementCache = [];

    public function __construct(
        Config $config,
        ?mysqli $existingConn = null
    ) {
        parent::__construct($config);

        if ($existingConn !== null) {
            $this->conn = $existingConn;
            return;
        }

        if (!$this->config->lazy) {
            $this->connect();
        }
    }

    public function isConnected(): bool
    {
        return $this->conn !== null;
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
            if (!empty($resolvedParams)) {
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams, cacheable: false);
                $res = null;
                try {
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res instanceof mysqli_result) {
                        while (is_array($row = $res->fetch_assoc())) {
                            /** @var array<string, mixed> $row */
                            $yielded++;
                            yield $row;
                        }
                    }
                } finally {
                    if ($res instanceof mysqli_result) {
                        $res->free();
                    }
                    $stmt->close();
                    $elapsedTime = (hrtime(true) - $startNs) / 1e9;
                    $this->logQuery($sql, $params, $elapsedTime, "cursor", $yielded);
                }
                return;
            }

            $res = $this->conn->query($sql, MYSQLI_USE_RESULT);
            try {
                if ($res instanceof mysqli_result) {
                    while (is_array($row = $res->fetch_assoc())) {
                        /** @var array<string, mixed> $row */
                        $yielded++;
                        yield $row;
                    }
                }
            } finally {
                if ($res instanceof mysqli_result) {
                    $res->free();
                }
                $elapsedTime = (hrtime(true) - $startNs) / 1e9;
                $this->logQuery($sql, $params, $elapsedTime, "cursor", $yielded);
            }
        } catch (mysqli_sql_exception $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function clearStatementCache(): void
    {
        foreach ($this->statementCache as $stmt) {
            $stmt->close();
        }
        $this->statementCache = [];
    }

    public function lastInsertId(): int|string
    {
        $this->assertConnected();
        $id = $this->conn->insert_id;
        return is_numeric($id) ? (int)$id : (string)$id;
    }

    public function escape(string $value): string
    {
        $this->assertConnected();
        return $this->conn->real_escape_string($value);
    }

    public function close(): void
    {
        $this->closed = true;
        $conn = $this->conn;
        if ($conn !== null) {
            $this->clearStatementCache();
            if ($this->transactionLevel > 0) {
                $this->doRollback();
                $this->transactionLevel = 0;
            }
            $conn->close();
            $this->conn = null;
        }
    }

    public function getMysqli(): mysqli
    {
        $this->assertConnected();
        return $this->conn;
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
        $this->assertConnected();

        try {
            if (!empty($resolvedParams)) {
                $isCached = $this->config->statementCacheSize > 0;
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams, $isCached);
                try {
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res instanceof mysqli_result) {
                        /** @var array<int, array<string, mixed>> $rows */
                        $rows = $res->fetch_all(MYSQLI_ASSOC);
                        $res->free();
                    } else {
                        $rows = [];
                    }

                    return $rows;
                } finally {
                    if ($isCached) {
                        $stmt->free_result();
                    } else {
                        $stmt->close();
                    }
                }
            }

            $res = $this->conn->query($originalSql);
            if ($res instanceof mysqli_result) {
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $res->fetch_all(MYSQLI_ASSOC);
                $res->free();
            } else {
                $rows = [];
            }

            return $rows;
        } catch (mysqli_sql_exception $e) {
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
        $this->assertConnected();

        try {
            if (!empty($resolvedParams)) {
                $isCached = $this->config->statementCacheSize > 0;
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams, $isCached);
                try {
                    $stmt->execute();

                    return (int)$stmt->affected_rows;
                } finally {
                    if ($isCached) {
                        $stmt->free_result();
                    } else {
                        $stmt->close();
                    }
                }
            }

            $this->conn->query($originalSql);

            return (int)$this->conn->affected_rows;
        } catch (mysqli_sql_exception $e) {
            throw new QueryException($e->getMessage(), $originalSql, $originalParams, $e->getCode(), $e);
        }
    }

    /**
     * @phpstan-assert !null $this->conn
     */
    protected function assertConnected(): void
    {
        if ($this->conn === null && $this->config->lazy && !$this->closed) {
            $this->connect();
        }

        if ($this->conn === null) {
            throw new ConnectionException("A conexão MySQLi está encerrada.");
        }
    }

    protected function doBeginTransaction(): void
    {
        $this->assertConnected();
        $this->conn->begin_transaction();
    }

    protected function doCommit(): void
    {
        $this->assertConnected();
        $this->conn->commit();
    }

    protected function doRollback(): void
    {
        $this->assertConnected();
        $this->conn->rollback();
    }

    protected function executeTransactionSql(string $sql): void
    {
        $this->assertConnected();
        $this->conn->query($sql);
    }

    private function connect(): void
    {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $conn = mysqli_init();
            if ($conn === false) {
                throw new ConnectionException("Falha ao inicializar extensão MySQLi.");
            }

            $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

            foreach ($this->config->options as $option => $value) {
                if (is_int($option) && (is_int($value) || is_string($value))) {
                    $conn->options($option, $value);
                }
            }

            $conn->real_connect(
                $this->config->host,
                (string)$this->config->username,
                (string)$this->config->password,
                $this->config->database,
                $this->config->port
            );

            $conn->set_charset($this->config->charset);

            if ($this->isCompatibleCollation($this->config->charset, $this->config->collation)) {
                $conn->query("SET NAMES '{$this->config->charset}' COLLATE '{$this->config->collation}'");
            }

            $this->conn = $conn;
        } catch (ConnectionException $e) {
            throw $e;
        } catch (Exception $e) {
            $code = is_numeric($e->getCode()) ? (int)$e->getCode() : 0;
            throw new ConnectionException("Falha ao conectar via MySQLi: " . $e->getMessage(), $code, $e);
        }
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function prepareStatement(string $sql, array $params, bool $cacheable = true): mysqli_stmt
    {
        $this->assertConnected();
        $stmt = $this->getPreparedStatement($sql, $cacheable);

        $types = "";
        $boundParams = [];
        foreach ($params as $param) {
            if (is_object($param)) {
                $param = self::normalizeParamValue($param);
            }
            $types .= match (true) {
                is_bool($param) => "i",
                is_int($param) => "i",
                is_float($param) => "d",
                default => "s",
            };
            $boundParams[] = is_bool($param) ? (int)$param : $param;
        }

        if (!empty($boundParams)) {
            $stmt->bind_param($types, ...$boundParams);
        }

        return $stmt;
    }

    private function getPreparedStatement(string $sql, bool $cacheable = true): mysqli_stmt
    {
        $this->assertConnected();

        if ($cacheable && $this->config->statementCacheSize > 0) {
            if (isset($this->statementCache[$sql])) {
                $stmt = $this->statementCache[$sql];
                unset($this->statementCache[$sql]);
                $this->statementCache[$sql] = $stmt;
                $stmt->reset();

                return $stmt;
            }

            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new QueryException("Erro ao preparar consulta MySQLi", $sql, [], $this->conn->errno);
            }

            if (count($this->statementCache) >= $this->config->statementCacheSize) {
                $evicted = array_shift($this->statementCache);
                $evicted->close();
            }
            $this->statementCache[$sql] = $stmt;

            return $stmt;
        }

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new QueryException("Erro ao preparar consulta MySQLi", $sql, [], $this->conn->errno);
        }

        return $stmt;
    }
}
