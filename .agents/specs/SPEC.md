# Especificação Técnica: heliomarpm/db-php

> **Pacote:** `heliomarpm/db-php`  
> **Versão:** 1.0.0 (Greenfield)  
> **Licença:** MIT  
> **PHP Mínimo:** >= 8.2  
> **Namespace:** `HeliomarPM\DbPHP\`  

---

## 1. Visão Geral e Proposta de Valor
`db-php` é uma biblioteca ultra-leve, de alta performance e com zero dependências externas de produção, projetada para conectar e executar consultas SQL com máxima ergonomia sem o peso de um ORM completo.

Projetada como par conceitual do `heliomarpm/linq-php`, ela provê extração rápida de dados com telemetria e ponte fluente direta para processamento em memória.

---

## 2. Requisitos Arquiteturais e Restrições
1. **Zero Dependências de Produção**: Apenas extensões nativas (`ext-pdo`, `ext-mysqli`, `ext-json`).
2. **Tipagem Estrita e PHP 8.2+**: Todo arquivo com `declare(strict_types=1);`, DTOs readonly, Union Types e tipagem completa.
3. **Aspas Duplas Padronizadas**: Estritamente `quote_type = double` (PSR-12 via Laravel Pint).
4. **Segurança contra Injeção de SQL**: Prepared Statements nativos obrigatórios com `ATTR_EMULATE_PREPARES = false`.
5. **Charset `utf8mb4` por padrão**: Totalmente aderente ao MySQL 8+ e emojis.
6. **Exceções Limpas**: Banimento total de HTML hardcoded em mensagens de erro; uso de `QueryException` com `$sql`, `$params` e `$driverCode`.

---

## 3. Design de Contratos e Interfaces
- `Contracts/DriverInterface`: Interface comum com `query`, `execute`, transações, `lastInsertId` e `escape`.
- `Results/QueryResult`: Resultado imutável com `$rows`, `$row`, `$count`, `$elapsedTime` e método `->linq()`.
- `Config`: DTO imutável de conexão.
- `DB`: Fachada e gerenciador estático multi-conexão.