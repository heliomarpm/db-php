<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Support;

use JsonSerializable;

final readonly class QueryRecord implements JsonSerializable
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        public string $sql,
        public array $params,
        public float $elapsedTime,
        public string $type = "query",
        public string $driver = "",
        public int $affectedRows = 0,
        public ?string $connection = null,
        public float $timestamp = 0.0
    ) {}

    /**
     * Indica se a consulta utilizou parâmetros nomeados (:chave).
     */
    public function isNamed(): bool
    {
        return !empty($this->params) && !array_is_list($this->params);
    }

    /**
     * Indica se a consulta utilizou parâmetros posicionais (?).
     */
    public function isPositional(): bool
    {
        return !empty($this->params) && array_is_list($this->params);
    }

    /**
     * Indica se a consulta possui algum parâmetro vinculado.
     */
    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    /**
     * Converte o registro de telemetria em um array associativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            "sql" => $this->sql,
            "params" => $this->params,
            "elapsed_time" => $this->elapsedTime,
            "elapsed_time_ms" => round($this->elapsedTime * 1000, 3),
            "type" => $this->type,
            "driver" => $this->driver,
            "affected_rows" => $this->affectedRows,
            "is_named" => $this->isNamed(),
            "connection" => $this->connection,
            "timestamp" => $this->timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
