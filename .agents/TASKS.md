# Backlog do Projeto: heliomarpm/db-php

> **Meta:** Evolução de estabilidade, resiliência, performance e consolidação da biblioteca db-php (v1.2)\
> **Status:** em desenvolvimento | **Progresso:** 10/17 (58%) | **WIP:** 0/2 | **Atualizado em:** 2026-10-26

## Concluídas recentemente

- [x] **T-010 · P1 · Otimizar subquery de contagem em PaginatesQueries removendo cláusula ORDER BY** (2026-10-26)
  - Evidência: Implementado método `removeTrailingOrderBy` no trait `PaginatesQueries` para podar a cláusula `ORDER BY` externa da subquery de `COUNT(*)`, mantendo compatibilidade com subqueries, literais e `GROUP BY`/`HAVING`. Testes unitários com casos simples, complexos, literais e `GROUP BY` 100% verdes (18 testes, 90 asserções). Demo testada e compatível.
- [x] **T-009 · P0 · Desacoplar auto-rollback interno de erros de query e execute nos drivers** (2026-10-26)
  - Evidência: Removido auto-rollback destrutivo de `query()` e `execute()` em `PdoDriver` e `MysqliDriver`. O método `transaction()` permanece como autoridade do ciclo atômico de `commit()` e `rollback()`. Teste unitário `testTransactionDoesNotRollbackWhenQueryErrorHandledInCallback` em `PdoDriverTest` 100% verde (16 testes, 80 asserções).
- [x] **T-001 · P1 · Setup do Repositório, Composer, Pint, PHPStan e Git** (2026-10-24)
- [x] **T-002 · P1 · Configuração Imutável e Hierarquia de Exceções Sem HTML** (2026-10-24)
- [x] **T-003 · P1 · QueryResult com Telemetria e Ponte Fluente para LinqPHP** (2026-10-24)
- [x] **T-004 · P1 · Implementação e Testes do PdoDriver (MySQL e SQLite)** (2026-10-24)
- [x] **T-005 · P1 · Implementação do MysqliDriver com Inferência de Tipos e Suporte a Parâmetros Nomeados** (2026-10-24)
- [x] **T-006 · P1 · Fachada DB e Gerenciador Multi-Conexão com Testes** (2026-10-24)
- [x] **T-007 · P1 · Documentação Completa (README), Laboratório Interativo (Demo), Suporte a Vercel e CI** (2026-10-24)
- [x] **T-008 · P1 · Paginação Nativa em Consultas com DTO PaginatedResult** (2026-10-24)

## Em progresso

*(Nenhuma tarefa em andamento no momento)*

## Backlog

### Alta Prioridade (Horizonte 1: Quick Wins & Higiene Imediata)

- [ ] **T-017 · P2 · Incluir exemplo interativo de controle manual de transações na demo (`beginTransaction`, `commit`, `rollback`)**
  - Origem: manual | Dep.: nenhuma
  - Ref: [demo/index.php](../demo/index.php), [DriverInterface.php](../src/Contracts/DriverInterface.php#L60-L75), [PdoDriver.php](../src/Drivers/PdoDriver.php#L83-L105)
  - DoD:
    - [ ] Adicionar novo cenário na demo (ex: `transacao_manual`) demonstrando o fluxo explícito com `beginTransaction()`, `execute()`, `commit()` e tratamento de exceção com `rollback()`
    - [ ] Exibir código PHP idiomático com bloco `try/catch` manual e telemetria de execução no laboratório
    - [ ] Adicionar pílula de navegação e garantir renderização consistente das tabelas de contas antes e depois da transação
    - [ ] Validar compatibilidade no navegador via subagente e manter testes unitários passando

- [ ] **T-011 · P1 · Corrigir inferência e bind de booleanos e nulos no MysqliDriver**
  - Origem: assessment A-07 | Dep.: nenhuma
  - Ref: [MysqliDriver.php](../src/Drivers/MysqliDriver.php#L231-L236)
  - DoD:
    - [ ] Mapear `is_bool($param)` explicitamente para tipo `"i"` e valor `(int)$param`
    - [ ] Assegurar bind seguro para parâmetros nulos sem conversão indevida para string vazia
    - [ ] Testes unitários validando passagem de booleanos em filtros e inserções

- [ ] **T-012 · P1 · Corrigir detecção de dialeto SQLite e parâmetros de conexão no PdoDriver e Config**
  - Origem: assessment A-05, A-06, A-09 | Dep.: nenhuma
  - Ref: [PdoDriver.php](../src/Drivers/PdoDriver.php#L194-L200), [Config.php](../src/Config.php#L33), [DB.php](../src/DB.php#L21), [README.md](../README.md#L73)
  - DoD:
    - [ ] No `PdoDriver`, validar `$this->config->driver === 'sqlite'` para montar DSN SQLite independente da extensão do arquivo
    - [ ] Em `Config::fromArray()`, aceitar tanto `hostname` quanto `host` para compatibilidade com documentação
    - [ ] Adicionar método `DB::setDefaultConnection(string $name): void`
    - [ ] Corrigir documentação do README.md alinhando parâmetros nomeados com a API

- [ ] **T-013 · P0 · Implementar suíte de testes unitários para MysqliDriver**
  - Origem: assessment A-02 | Dep.: T-009, T-011
  - Ref: [MysqliDriver.php](../src/Drivers/MysqliDriver.php), [tests/Unit/PdoDriverTest.php](../tests/Unit/PdoDriverTest.php)
  - DoD:
    - [ ] Criar `tests/Unit/MysqliDriverTest.php` cobrindo conexões, queries, execute, transações e binds
    - [ ] Testar cenários de sucesso e falhas com asserção de `QueryException`
    - [ ] Suíte validada no CI do GitHub Actions

### Média Prioridade (Horizonte 2: Consolidação & Refatoração Tática)

- [ ] **T-014 · P2 · Extrair classe base abstrata AbstractDriver consolidando métodos comuns**
  - Origem: assessment C-01 | Dep.: T-009, T-013
  - Ref: [PdoDriver.php](../src/Drivers/PdoDriver.php), [MysqliDriver.php](../src/Drivers/MysqliDriver.php), [DriverInterface.php](../src/Contracts/DriverInterface.php)
  - DoD:
    - [ ] Criar `HeliomarPM\DbPHP\Drivers\AbstractDriver` implementando `DriverInterface`
    - [ ] Centralizar implementação de `transaction()`, `countAffected()` e trait `PaginatesQueries`
    - [ ] Refatorar `PdoDriver` e `MysqliDriver` estendendo `AbstractDriver` sem alteração de comportamento externo
    - [ ] 100% dos testes unitários mantidos verdes

- [ ] **T-015 · P2 · Parser léxico seguro para parâmetros nomeados no MysqliDriver**
  - Origem: assessment A-04 | Dep.: T-013
  - Ref: [MysqliDriver.php](../src/Drivers/MysqliDriver.php#L247-L273)
  - DoD:
    - [ ] Substituir regex simples por parser que ignore literais entre aspas simples/duplas e funções de data com `:`
    - [ ] Suportar múltiplos usos da mesma chave nomeada na query (ex: `:termo` repetido)
    - [ ] Testes unitários cobrindo literais com dois pontos e múltiplos parâmetros nomeados idênticos

- [ ] **T-016 · P3 · Consolidar manipulação de resultados e padronização JSON entre QueryResult e PaginatedResult**
  - Origem: assessment C-02, C-03, A-08 | Dep.: nenhuma
  - Ref: [QueryResult.php](../src/Results/QueryResult.php), [PaginatedResult.php](../src/Results/PaginatedResult.php)
  - DoD:
    - [ ] Extrair lógica compartilhada (`column()`, `firstOrDefault()`, `linq()`, `count()`) via composição ou trait interna
    - [ ] Padronizar convenção de chaves JSON (documentando e mantendo retrocompatibilidade)
    - [ ] Eliminar necessidade de `$count` obrigatório em `QueryResult` calculando automaticamente via `count($rows)`
    - [ ] Testes de serialização e manipulação validados em `QueryResultTest` e `PaginatedResultTest`