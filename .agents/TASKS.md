# Backlog do Projeto: heliomarpm/db-php

> **Meta:** Construção da biblioteca leve de banco de dados db-php com PDO, MySQLi, telemetria e interop com linq-php\
> **Status:** em desenvolvimento | **Progresso:** 7/8 (87%) | **Atualizado em:** 2026-10-24

## Concluídas recentemente

- [x] **T-001 · P1 · Setup do Repositório, Composer, Pint, PHPStan e Git** (2026-10-24)
- [x] **T-002 · P1 · Configuração Imutável e Hierarquia de Exceções Sem HTML** (2026-10-24)
- [x] **T-003 · P1 · QueryResult com Telemetria e Ponte Fluente para LinqPHP** (2026-10-24)
- [x] **T-004 · P1 · Implementação e Testes do PdoDriver (MySQL e SQLite)** (2026-10-24)
- [x] **T-005 · P1 · Implementação do MysqliDriver com Inferência de Tipos e Suporte a Parâmetros Nomeados** (2026-10-24)
- [x] **T-006 · P1 · Fachada DB e Gerenciador Multi-Conexão com Testes** (2026-10-24)
- [x] **T-007 · P1 · Documentação Completa (README), Laboratório Interativo (Demo), Suporte a Vercel e CI** (2026-10-24)

## Em progresso

*(Nenhuma tarefa em andamento no momento)*

## Backlog

- [ ] **T-008 · P1 · Paginação Nativa em Consultas com DTO PaginatedResult**
  - **Origem:** manual (requisito de produto)
  - **Contexto:** Facilitar a paginação de queries SQL sem exigir que o desenvolvedor calcule manualmente offsets, execute duas queries separadas ou monte estruturas de resposta para APIs.
  - **Escopo e Design:**
    - Método na fachada e nos drivers: `DB::paginate(string $sql, array $params = [], int $page = 1, int $perPage = 15): PaginatedResult`.
    - Execução automática de contagem total (`COUNT(*)`) otimizada e injeção segura de `LIMIT` e `OFFSET`.
    - DTO imutável `PaginatedResult` contendo:
      - `items`: array associativo das linhas da página atual (`rows`).
      - `total`: contagem total de registros encontrados.
      - `perPage`: limite de itens por página.
      - `currentPage`: página atual (1-indexed).
      - `lastPage`: total de páginas calculadas (`ceil(total / perPage)`).
      - `from`: índice do primeiro item da página atual.
      - `to`: índice do último item da página atual.
      - `hasMorePages`: booleano indicando se há páginas seguintes.
      - `elapsedTime`: tempo total de execução somado (contagem + busca).
    - Serialização JSON nativa (`JsonSerializable`) com formato padronizado para APIs REST.
  - **Critérios de Aceite:**
    - Paginação funcional em ambos os drivers (`PdoDriver` e `MysqliDriver`).
    - Suporte a parâmetros nomeados (`:param`) e posicionais (`?`) mantidos intactos tanto no `COUNT(*)` quanto na query paginada.
    - Testes unitários cobrindo primeira página, página intermediária, última página e página vazia além do limite.
    - Demonstração do recurso adicionada ao `README.md` e novo cenário interativo em `demo/index.php`.
    - 100% aprovado no `composer check` (Pint + PHPStan Level 8 + PHPUnit).