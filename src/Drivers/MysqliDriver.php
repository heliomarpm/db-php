<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use Exception;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use HeliomarPM\DbPHP\Results\QueryResult;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;

final class MysqliDriver extends AbstractDriver
{
    private ?mysqli $conn;

    public function __construct(
        Config $config,
        ?mysqli $existingConn = null
    ) {
        parent::__construct($config);

        if ($existingConn !== null) {
            $this->conn = $existingConn;
            return;
        }

        $this->connect();
    }


    public function query(string $sql, array $params = []): QueryResult
    {
        $this->assertConnected();
        $startTime = microtime(true);

        [$resolvedSql, $resolvedParams] = $this->resolveNamedParameters($sql, $params);

        try {
            if (!empty($resolvedParams)) {
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams);
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
                    $this->affectedRows = (int)$stmt->affected_rows;
                } finally {
                    $stmt->close();
                }
            } else {
                $res = $this->conn->query($sql);
                if ($res instanceof mysqli_result) {
                    /** @var array<int, array<string, mixed>> $rows */
                    $rows = $res->fetch_all(MYSQLI_ASSOC);
                    $res->free();
                } else {
                    $rows = [];
                }
                $this->affectedRows = (int)$this->conn->affected_rows;
            }

            $elapsedTime = microtime(true) - $startTime;
            $this->logQuery($sql, $params, $elapsedTime, "query", count($rows));

            return new QueryResult(
                rows: $rows,
                count: count($rows),
                elapsedTime: $elapsedTime
            );
        } catch (mysqli_sql_exception $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->assertConnected();
        $startTime = microtime(true);

        [$resolvedSql, $resolvedParams] = $this->resolveNamedParameters($sql, $params);

        try {
            if (!empty($resolvedParams)) {
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams);
                try {
                    $stmt->execute();
                    $this->affectedRows = (int)$stmt->affected_rows;
                } finally {
                    $stmt->close();
                }
            } else {
                $this->conn->query($sql);
                $this->affectedRows = (int)$this->conn->affected_rows;
            }

            $elapsedTime = microtime(true) - $startTime;
            $this->logQuery($sql, $params, $elapsedTime, "execute", $this->affectedRows);

            return $this->affectedRows;
        } catch (mysqli_sql_exception $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
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
        $conn = $this->conn;
        if ($conn !== null) {
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
     * @phpstan-assert !null $this->conn
     */
    protected function assertConnected(): void
    {
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
     * @param array<int, mixed> $params
     */
    private function prepareStatement(string $sql, array $params): mysqli_stmt
    {
        $this->assertConnected();
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new QueryException("Erro ao preparar consulta MySQLi", $sql, $params, $this->conn->errno);
        }

        $types = "";
        $boundParams = [];
        foreach ($params as $param) {
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
}
