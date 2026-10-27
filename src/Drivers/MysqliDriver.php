<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use Exception;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use HeliomarPM\DbPHP\Results\QueryResult;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;

final class MysqliDriver extends AbstractDriver
{
    private ?mysqli $conn;
    private bool $inTransaction = false;

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
                $stmt->execute();
                $res = $stmt->get_result();
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                $this->affectedRows = (int)$stmt->affected_rows;
                $stmt->close();
            } else {
                $res = $this->conn->query($sql);
                /** @var array<int, array<string, mixed>> $rows */
                $rows = is_object($res) ? $res->fetch_all(MYSQLI_ASSOC) : [];
                $this->affectedRows = (int)$this->conn->affected_rows;
            }

            $elapsedTime = microtime(true) - $startTime;

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

        [$resolvedSql, $resolvedParams] = $this->resolveNamedParameters($sql, $params);

        try {
            if (!empty($resolvedParams)) {
                $stmt = $this->prepareStatement($resolvedSql, $resolvedParams);
                $stmt->execute();
                $this->affectedRows = (int)$stmt->affected_rows;
                $stmt->close();
            } else {
                $this->conn->query($sql);
                $this->affectedRows = (int)$this->conn->affected_rows;
            }

            return $this->affectedRows;
        } catch (mysqli_sql_exception $e) {
            throw new QueryException($e->getMessage(), $sql, $params, $e->getCode(), $e);
        }
    }

    public function beginTransaction(): void
    {
        $this->assertConnected();
        if (!$this->inTransaction) {
            $this->conn->begin_transaction();
            $this->inTransaction = true;
        }
    }

    public function commit(): void
    {
        $this->assertConnected();
        if ($this->inTransaction) {
            try {
                $this->conn->commit();
            } finally {
                $this->inTransaction = false;
            }
        }
    }

    public function rollback(): void
    {
        $this->assertConnected();
        if ($this->inTransaction) {
            try {
                $this->conn->rollback();
            } finally {
                $this->inTransaction = false;
            }
        }
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
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
            if ($this->inTransaction) {
                $this->rollback();
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

    private function connect(): void
    {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $this->conn = new mysqli(
                $this->config->host,
                (string)$this->config->username,
                (string)$this->config->password,
                $this->config->database,
                $this->config->port
            );

            $this->conn->set_charset($this->config->charset);
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

    /**
     * @phpstan-assert !null $this->conn
     */
    private function assertConnected(): void
    {
        if ($this->conn === null) {
            throw new ConnectionException("A conexão MySQLi está encerrada.");
        }
    }
}
