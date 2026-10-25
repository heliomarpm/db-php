<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP;

use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Drivers\MysqliDriver;
use HeliomarPM\DbPHP\Drivers\PdoDriver;
use HeliomarPM\DbPHP\Exceptions\ConnectionException;
use HeliomarPM\DbPHP\Results\QueryResult;
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
     * @param array<int|string, mixed> $params
     */
    public static function query(string $sql, array $params = []): QueryResult
    {
        return self::connection()->query($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::connection()->execute($sql, $params);
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
    }
}
