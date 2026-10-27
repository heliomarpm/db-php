<?php

declare(strict_types=1);

namespace Demo\Support;

use Demo\Exceptions\SecurityException;

/**
 * Valida instruções SQL no playground da demo, restringindo à leitura segura.
 *
 * Esta classe pertence exclusivamente à aplicação demo e NÃO faz parte
 * da biblioteca heliomarpm/db-php. Mantenha-a dentro do diretório demo/.
 */
final class SqlSandboxValidator
{
    public const MAX_SQL_LENGTH = 2048;

    private const FORBIDDEN_WORDS = [
        'ATTACH',
        'DETACH',
        'PRAGMA',
        'LOAD_EXTENSION',
        'WRITEFILE',
        'READFILE',
        'EDIT',
        'VACUUM',
        'REINDEX',
        'DROP',
        'DELETE',
        'UPDATE',
        'INSERT',
        'ALTER',
        'CREATE',
        'REPLACE',
        'TRUNCATE',
        'TRANSACTION',
        'COMMIT',
        'ROLLBACK',
    ];

    /**
     * Valida se a instrução SQL é estritamente segura para execução em ambiente de sandbox (apenas leitura).
     *
     * @throws SecurityException Se a consulta contiver comandos perigosos, stacked queries ou sintaxe não permitida.
     */
    public static function assertSafe(string $sql): void
    {
        if (strlen($sql) > self::MAX_SQL_LENGTH) {
            throw new SecurityException('A consulta excede o tamanho máximo permitido de ' . self::MAX_SQL_LENGTH . ' caracteres.');
        }

        $trimmedRaw = trim($sql);
        if ($trimmedRaw === '') {
            throw new SecurityException('A consulta SQL não pode ser vazia.');
        }

        // 1. Remove literais de texto e comentários para inspecionar somente a gramática executável
        // Padrão cobre: strings em aspas simples (com '' e \'), aspas duplas (com "" e \"),
        // identificadores em crases, comentários de linha (-- e #) e blocos (/* ... */)
        $cleanSql = (string)preg_replace(
            "/('(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"|`(?:[^`\\\\]|\\\\.)*`|(?:--|#)[^\\r\\n]*|\\/\\*[\\s\\S]*?\\*\\/)/",
            ' ',
            $sql
        );
        $cleanSqlTrimmed = trim((string)$cleanSql);

        if ($cleanSqlTrimmed === '') {
            throw new SecurityException('A consulta não contém instruções SQL executáveis válidas.');
        }

        // 2. Bloquear stacked queries (múltiplos comandos separados por ponto e vírgula)
        $withoutTrailingSemicolon = rtrim($cleanSqlTrimmed, " \t\n\r\0\x0B;");
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw new SecurityException('Execução de múltiplas consultas concatenadas por ponto e vírgula não é permitida no playground.');
        }

        // 3. Garantir que a instrução comece estritamente por comando de leitura (SELECT, EXPLAIN ou WITH)
        if (! preg_match('/^(SELECT|EXPLAIN|WITH)\b/i', $cleanSqlTrimmed)) {
            throw new SecurityException('Apenas instruções de leitura (SELECT ou EXPLAIN) são permitidas no playground.');
        }

        // 4. Bloquear comandos destrutivos ou de sistema no corpo da consulta
        $pattern = '/\b(' . implode('|', self::FORBIDDEN_WORDS) . ')\b/i';
        if (preg_match($pattern, $cleanSqlTrimmed, $match)) {
            $keyword = strtoupper($match[1]);
            throw new SecurityException("O comando ou função '{$keyword}' não é permitido no playground.");
        }
    }

    /**
     * Retorna true se a SQL for considerada segura para sandbox, ou false caso contrário.
     */
    public static function isSafe(string $sql): bool
    {
        try {
            self::assertSafe($sql);

            return true;
        } catch (SecurityException) {
            return false;
        }
    }
}
