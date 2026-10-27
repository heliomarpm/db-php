<?php

declare(strict_types=1);

namespace HeliomarPM\DbPHP\Drivers;

use HeliomarPM\DbPHP\Concerns\PaginatesQueries;
use HeliomarPM\DbPHP\Config;
use HeliomarPM\DbPHP\Contracts\DriverInterface;
use HeliomarPM\DbPHP\Exceptions\QueryException;
use Throwable;

abstract class AbstractDriver implements DriverInterface
{
    use PaginatesQueries;

    protected int $affectedRows = 0;

    public function __construct(
        protected readonly Config $config
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Converte parâmetros nomeados em posicionais (?) ignorando literais de texto,
     * identificadores, comentários SQL e operadores especiais (::, :=).
     *
     * @param array<int|string, mixed> $params
     * @return array{0: string, 1: list<mixed>}
     * @throws QueryException
     */
    public static function resolveNamedParameters(string $sql, array $params): array
    {
        if (empty($params) || array_is_list($params)) {
            return [$sql, $params];
        }

        $pattern = '/(
            \'(?:[^\'\\\\]|\\\\.|\'\')*\'
            |
            "(?:[^"\\\\]|\\\\.|"")*"
            |
            `(?:[^`\\\\]|\\\\.|``)*`
            |
            (?:--|\#)[^\r\n]*
            |
            \/\*[\s\S]*?\*\/
        )
        |
        (::|:=)
        |
        (?<!:):([a-zA-Z_][a-zA-Z0-9_]*)
        /xs';

        $orderedParams = [];
        $translatedSql = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($params, &$orderedParams): string {
                if (!empty($matches[1]) || !empty($matches[2])) {
                    return $matches[0];
                }

                $key = $matches[3];
                if (array_key_exists($key, $params)) {
                    $orderedParams[] = $params[$key];
                    return "?";
                }
                if (array_key_exists(":" . $key, $params)) {
                    $orderedParams[] = $params[":" . $key];
                    return "?";
                }

                return $matches[0];
            },
            $sql
        );

        if ($translatedSql === null) {
            throw new QueryException("Erro ao processar parâmetros na consulta SQL: " . preg_last_error_msg(), $sql, $params);
        }

        return [$translatedSql, $orderedParams];
    }

    public function countAffected(): int
    {
        return $this->affectedRows;
    }

    public function getConfig(): Config
    {
        return $this->config;
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
}
