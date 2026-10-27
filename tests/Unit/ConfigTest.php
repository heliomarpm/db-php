<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Tests\Unit;

use HeliomarPM\DbPHP\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new Config();

        $this->assertSame("pdo", $config->driver);
        $this->assertSame("127.0.0.1", $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertSame("", $config->database);
        $this->assertSame("root", $config->username);
        $this->assertSame("", $config->password);
        $this->assertSame("utf8mb4", $config->charset);
        $this->assertSame("utf8mb4_unicode_ci", $config->collation);
    }

    public function testFromArray(): void
    {
        $config = Config::fromArray([
            "driver" => "mysqli",
            "host" => "localhost",
            "port" => 3307,
            "database" => "minha_base",
            "username" => "usuario",
            "password" => "secreta",
            "charset" => "utf8mb4",
        ]);

        $this->assertSame("mysqli", $config->driver);
        $this->assertSame("localhost", $config->host);
        $this->assertSame(3307, $config->port);
        $this->assertSame("minha_base", $config->database);
        $this->assertSame("usuario", $config->username);
        $this->assertSame("secreta", $config->password);
    }

    public function testForSqlite(): void
    {
        $config = Config::forSqlite(":memory:");

        $this->assertSame("pdo", $config->driver);
        $this->assertSame(":memory:", $config->database);
        $this->assertNull($config->username);
        $this->assertNull($config->password);
    }

    public function testFromArrayWithHostnameAndAliases(): void
    {
        $config = Config::fromArray([
            "hostname" => "192.168.1.50",
            "user" => "custom_user",
            "pass" => "custom_pass",
            "database" => "test_db",
        ]);

        $this->assertSame("192.168.1.50", $config->host);
        $this->assertSame("custom_user", $config->username);
        $this->assertSame("custom_pass", $config->password);
    }
}
