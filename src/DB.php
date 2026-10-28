<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP;

use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\MysqliDriver;
use HeliomarPM\DbPHP\Drivers\PdoDriver;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Results\PaginatedResult;
use HeliomarPM\DbPHP\Results\QueryResult;
use HeliomarPM\DbPHP\Support\QueryLog;
use HeliomarPM\DbPHP\Support\QueryRecord;
use InvalidArgumentException;

final class DB
{
    /**
     * @var array<string, DriverInterface>
     */
    private static array $connections = [];
    private static string $defaultConnection = "default";

    /**
     * @param Config|array<string, mixed> $config
     */
    public static function connect(Config|array $config, string $name = "default"): DriverInterface
    {
        $configObj = is_array($config) ? Config::fromArray($config) : $config;

        $driver = match (strtolower($configObj->driver)) {
            "pdo", "sqlite", "mysql" => new PdoDriver($configObj),
            "mysqli" => new MysqliDriver($configObj),
            default => throw new InvalidArgumentException("Driver não suportado: {$configObj->driver}"),
        };

        self::$connections[$name] = $driver;

        return $driver;
    }

    public static function connection(?string $name = null): DriverInterface
    {
        $connName = $name ?? self::$defaultConnection;

        if (!isset(self::$connections[$connName])) {
            throw new ConnectionException("Nenhuma conexão ativa encontrada com o nome '{$connName}'.");
        }

        return self::$connections[$connName];
    }

    public static function addConnection(string $name, DriverInterface $driver): void
    {
        self::$connections[$name] = $driver;
    }

    /**
     * Define o nome da conexão padrão utilizada pelas chamadas estáticas da fachada.
     */
    public static function setDefaultConnection(string $name): void
    {
        if (trim($name) === "") {
            throw new InvalidArgumentException("O nome da conexão padrão não pode ser vazio.");
        }

        self::$defaultConnection = $name;
    }

    /**
     * Retorna o nome da conexão padrão configurada.
     */
    public static function getDefaultConnection(): string
    {
        return self::$defaultConnection;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function query(string $sql, array $params = []): QueryResult
    {
        return self::connection()->query($sql, $params);
    }

    /**
     * Executa consulta paginada na conexão padrão ou ativa.
     *
     * @param array<int|string, mixed> $params
     */
    public static function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): PaginatedResult
    {
        return self::connection()->paginate($sql, $params, $page, $perPage);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::connection()->execute($sql, $params);
    }

    /**
     * Retorna o ID gerado pelo último INSERT na conexão ativa.
     */
    public static function lastInsertId(): int|string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * @template TReturn
     * @param callable(DriverInterface): TReturn $callback
     * @return TReturn
     */
    public static function transaction(callable $callback): mixed
    {
        return self::connection()->transaction($callback);
    }

    /**
     * Inicia explicitamente uma nova transação na conexão ativa.
     */
    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    /**
     * Confirma explicitamente a transação ativa na conexão ativa.
     */
    public static function commit(): void
    {
        self::connection()->commit();
    }

    /**
     * Reverte explicitamente a transação ativa na conexão ativa.
     */
    public static function rollback(): void
    {
        self::connection()->rollback();
    }

    /**
     * Verifica se há uma transação ativa na conexão ativa.
     */
    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    public static function sqlite(string $database = ":memory:", string $name = "default"): DriverInterface
    {
        return self::connect(Config::forSqlite($database), $name);
    }

    public static function mysql(
        string $database,
        string $host = "127.0.0.1",
        int $port = 3306,
        string $username = "root",
        string $password = "",
        string $driver = "pdo",
        string $name = "default"
    ): DriverInterface {
        $config = new Config(
            driver: $driver,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password
        );

        return self::connect($config, $name);
    }

    public static function disconnect(?string $name = null): void
    {
        if ($name !== null) {
            if (isset(self::$connections[$name])) {
                self::$connections[$name]->close();
                unset(self::$connections[$name]);
            }
            return;
        }

        foreach (self::$connections as $driver) {
            $driver->close();
        }
        self::$connections = [];
        self::$defaultConnection = "default";
    }

    /**
     * Ativa ou desativa o modo de debug global para coleta de métricas e consultas.
     */
    public static function setDebug(bool $enabled): void
    {
        QueryLog::setEnabled($enabled);
    }

    /**
     * Verifica se o modo de debug global está ativado.
     */
    public static function isDebug(): bool
    {
        return QueryLog::isEnabled();
    }

    /**
     * Retorna a lista de consultas registradas no coletor de telemetria.
     *
     * @return list<QueryRecord>
     */
    public static function getQueryLog(): array
    {
        return QueryLog::all();
    }

    /**
     * Retorna métricas agregadas de execução das consultas registradas.
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
    public static function getQueryMetrics(): array
    {
        return QueryLog::metrics();
    }

    /**
     * Limpa o histórico de consultas e métricas registradas.
     */
    public static function clearQueryLog(): void
    {
        QueryLog::reset();
    }
}
