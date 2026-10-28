<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP;

final readonly class Config
{
    /**
     * @param array<int|string, mixed> $options
     */
    public function __construct(
        public string $driver = "pdo",
        public string $host = "127.0.0.1",
        public int $port = 3306,
        public string $database = "",
        public ?string $username = "root",
        public ?string $password = "",
        public string $charset = "utf8mb4",
        public string $collation = "utf8mb4_unicode_ci",
        public array $options = [],
        public bool $debug = false
    ) {}

    /**
     * Cria configuração a partir de um array associativo.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            driver: (string)($data["driver"] ?? "pdo"),
            host: (string)($data["host"] ?? $data["hostname"] ?? "127.0.0.1"),
            port: (int)($data["port"] ?? 3306),
            database: (string)($data["database"] ?? ""),
            username: isset($data["username"]) ? (string)$data["username"] : (isset($data["user"]) ? (string)$data["user"] : null),
            password: isset($data["password"]) ? (string)$data["password"] : (isset($data["pass"]) ? (string)$data["pass"] : null),
            charset: (string)($data["charset"] ?? "utf8mb4"),
            collation: (string)($data["collation"] ?? "utf8mb4_unicode_ci"),
            options: (array)($data["options"] ?? []),
            debug: (bool)($data["debug"] ?? false)
        );
    }

    /**
     * Cria configuração simplificada para SQLite (memória ou arquivo).
     */
    public static function forSqlite(string $database = ":memory:", bool $debug = false): self
    {
        return new self(
            driver: "pdo",
            host: "localhost",
            port: 0,
            database: $database,
            username: null,
            password: null,
            charset: "utf8",
            debug: $debug
        );
    }

    /**
     * Cria configuração para MySQL com credenciais padrão.
     */
    public static function forMysql(
        string $database,
        string $host = "127.0.0.1",
        int $port = 3306,
        string $username = "root",
        string $password = "",
        string $charset = "utf8mb4",
        bool $debug = false
    ): self {
        return new self(
            driver: "pdo",
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            debug: $debug
        );
    }

    /**
     * Retorna uma nova instância de configuração com o modo debug alterado.
     */
    public function withDebug(bool $debug = true): self
    {
        return new self(
            driver: $this->driver,
            host: $this->host,
            port: $this->port,
            database: $this->database,
            username: $this->username,
            password: $this->password,
            charset: $this->charset,
            collation: $this->collation,
            options: $this->options,
            debug: $debug
        );
    }
}
