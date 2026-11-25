<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Support;

final class QueryLog
{
    private static bool $enabled = false;
    private static int $maxRecords = 500;

    /**
     * @var list<QueryRecord>
     */
    private static array $records = [];

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Define o limite máximo de registros retidos em memória (0 = ilimitado).
     */
    public static function setMaxRecords(int $max): void
    {
        self::$maxRecords = max(0, $max);

        if (self::$maxRecords > 0 && count(self::$records) > self::$maxRecords) {
            self::$records = array_slice(self::$records, -self::$maxRecords);
        }
    }

    public static function getMaxRecords(): int
    {
        return self::$maxRecords;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function record(
        string $sql,
        array $params,
        float $elapsedTime,
        string $type = "query",
        string $driver = "",
        int $affectedRows = 0,
        ?string $connection = null
    ): void {
        self::$records[] = new QueryRecord(
            sql: $sql,
            params: $params,
            elapsedTime: $elapsedTime,
            type: $type,
            driver: $driver,
            affectedRows: $affectedRows,
            connection: $connection,
            timestamp: microtime(true)
        );

        if (self::$maxRecords > 0 && count(self::$records) > self::$maxRecords) {
            array_shift(self::$records);
        }
    }

    /**
     * Retorna a lista cronológica de todas as consultas registradas.
     *
     * @return list<QueryRecord>
     */
    public static function all(): array
    {
        return self::$records;
    }

    /**
     * Retorna o total de consultas registradas.
     */
    public static function count(): int
    {
        return count(self::$records);
    }

    /**
     * Retorna a soma de tempo de execução de todas as consultas registradas em segundos.
     */
    public static function totalTime(): float
    {
        $total = 0.0;
        foreach (self::$records as $record) {
            $total += $record->elapsedTime;
        }

        return $total;
    }

    /**
     * Limpa todos os registros de telemetria acumulados.
     */
    public static function reset(): void
    {
        self::$records = [];
    }

    /**
     * Retorna métricas agregadas das consultas executadas.
     *
     * @return array{
     *     total_queries: int,
     *     total_time: float,
     *     total_time_ms: float,
     *     named_queries: int,
     *     positional_queries: int,
     *     no_param_queries: int,
     *     queries_by_type: array<string, int>
     * }
     */
    public static function metrics(): array
    {
        $totalTime = 0.0;
        $named = 0;
        $positional = 0;
        $noParam = 0;
        $byType = [];

        foreach (self::$records as $record) {
            $totalTime += $record->elapsedTime;

            if (!$record->hasParams()) {
                $noParam++;
            } elseif ($record->isNamed()) {
                $named++;
            } else {
                $positional++;
            }

            $byType[$record->type] = ($byType[$record->type] ?? 0) + 1;
        }

        return [
            "total_queries" => count(self::$records),
            "total_time" => $totalTime,
            "total_time_ms" => round($totalTime * 1000, 3),
            "named_queries" => $named,
            "positional_queries" => $positional,
            "no_param_queries" => $noParam,
            "queries_by_type" => $byType,
        ];
    }
}
