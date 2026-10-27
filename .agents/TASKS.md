# Backlog do Projeto: heliomarpm/db-php

> **Meta:** Evolução de estabilidade, resiliência, performance e consolidação da biblioteca db-php (v1.2)\
> **Status:** em desenvolvimento | **Progresso:** 16/18 (88%) | **WIP:** 0/2 | **Atualizado em:** 2026-10-27

## Concluídas recentemente

- [x] **T-015 · P2 · Parser léxico seguro para parâmetros nomeados no MysqliDriver** (2026-10-27)
  - Evidência: Substituído parser regex ingênuo em `MysqliDriver::resolveNamedParameters` por analisador léxico seguro que identifica e preserva literais em aspas simples (`\'`, `''`), aspas duplas (`\"`, `""`), identificadores entre crases (`` ` ``), comentários de linha (`--`, `#`), comentários em bloco (`/* ... */`) e operadores especiais de banco (`::`, `:=`). Adicionado suporte a parâmetros repetidos (mesma chave substituída por `?` e replicada ordenadamente no array posicional) e chaves com ou sem prefixo de dois-pontos (`:id` e `id`). Suíte de testes unitários e de integração adicionada em `tests/Unit/MysqliDriverTest.php`, elevando a suíte para 37 testes e 187 asserções 100% verdes. Análise estática com PHPStan Nível 8 com 0 erros e Pint 100% aprovado.
- [x] **T-014 · P2 · Extrair classe base abstrata AbstractDriver consolidando métodos comuns** (2026-10-27)
  - Evidência: Criada classe abstrata `AbstractDriver` implementando `DriverInterface`, centralizando trait `PaginatesQueries`, propriedade `$affectedRows`, métodos `transaction()`, `countAffected()` e `__destruct()`. `PdoDriver` e `MysqliDriver` refatorados estendendo `AbstractDriver` e eliminando código duplicado. Suíte completa de 35 testes e 167 asserções 100% verde sem regressões. Análise estática com PHPStan Nível 8 com 0 erros.
- [x] **T-013 · P0 · Implementar suíte de testes unitários para MysqliDriver** (2026-10-27)
  - Evidência: Criada suíte completa em `tests/Unit/MysqliDriverTest.php` cobrindo conexões válidas e com erro (`ConnectionException`), `execute()`, `lastInsertId()`, `countAffected()`, `query()`, binds posicionais e nomeados, transações automáticas e manuais (`beginTransaction`, `commit`, `rollback`, `inTransaction`), persistência da transação após tratamento de exceção local, `escape()` e paginação nativa. Configurado service container do MySQL no pipeline `.github/workflows/ci.yml`. Suíte com 11 testes e 55 asserções 100% verdes (35 testes no total da biblioteca).
- [x] **T-017 · P2 · Incluir exemplo interativo de controle manual de transações na demo (`beginTransaction`, `commit`, `rollback`)** (2026-10-27)
  - Evidência: Adicionado novo cenário interativo `transacao_manual` na demo com seletor de modo `commit` e `rollback`. Implementados métodos estáticos delegadores `DB::beginTransaction()`, `DB::commit()`, `DB::rollback()` e `DB::inTransaction()` na fachada. Tabela de contas enriquecida com saldos anteriores, variações monetárias calculadas e badges de ciclo transacional. Testes unitários em `DBTest` cobrindo fluxo manual de commit e rollback 100% verdes (25 testes, 125 asserções). Validado no navegador via subagente com gravação e screenshot.
- [x] **T-012 · P1 · Corrigir detecção de dialeto SQLite e parâmetros de conexão no PdoDriver e Config** (2026-10-27)
  - Evidência: Atualizado `PdoDriver` para reconhecer `driver === 'sqlite'` e extensões `.db`/`.sqlite3`, permitindo conexão SQLite mesmo sem sufixo `.sqlite`. `Config::fromArray()` atualizado para aceitar tanto `host` quanto `hostname` (além de `user` e `pass`). Adicionados métodos `DB::setDefaultConnection()` e `DB::getDefaultConnection()` na fachada estática com reset automático em `DB::disconnect()`. Documentação do README.md alinhada com parâmetros nomeados (`host:`). Testes unitários em `ConfigTest`, `DBTest` e `PdoDriverTest` 100% verdes (23 testes, 116 asserções).
- [x] **T-011 · P1 · Corrigir inferência e bind de booleanos e nulos no MysqliDriver** (2026-10-27)
  - Evidência: Mapeado `is_bool($param)` explicitamente para tipo inteiro `"i"` e valor `(int)$param` em `MysqliDriver::prepareStatement`, eliminando o erro MySQL 1366 (`Incorrect integer value: '' for column`) causado por conversão implícita de `false` para string vazia em strict mode. Adicionado suporte seguro a `null` sem coerção para string. Suíte unitária `MysqliDriverTest` cobrindo filtros e inserções com `true`, `false`, `null`, `float` e `string` 100% verde (19 testes, 103 asserções).
- [x] **T-010 · P1 · Otimizar subquery de contagem em PaginatesQueries removendo cláusula ORDER BY** (2026-10-26)
  - Evidência: Implementado método `removeTrailingOrderBy` no trait `PaginatesQueries` para podar a cláusula `ORDER BY` externa da subquery de `COUNT(*)`, mantendo compatibilidade com subqueries, literais e `GROUP BY`/`HAVING`. Testes unitários com casos simples, complexos, literais e `GROUP BY` 100% verdes (18 testes, 90 asserções). Demo testada e compatível.
- [x] **T-009 · P0 · Desacoplar auto-rollback interno de erros de query e execute nos drivers** (2026-10-26)
  - Evidência: Removido auto-rollback destrutivo de `query()` e `execute()` em `PdoDriver` e `MysqliDriver`. O método `transaction()` permanece como autoridade do ciclo atômico de `commit()` e `rollback()`. Teste unitário `testTransactionDoesNotRollbackWhenQueryErrorHandledInCallback` em `PdoDriverTest` 100% verde (16 testes, 80 asserções).

## Em progresso

*(Nenhuma tarefa em andamento no momento)*

## Backlog

### Alta Prioridade (Horizonte 1: Quick Wins & Higiene Imediata)

*(Nenhuma tarefa de alta prioridade restante)*

### Média Prioridade (Horizonte 2: Consolidação & Refatoração Tática)

- [ ] **T-016 · P3 · Consolidar manipulação de resultados e padronização JSON entre QueryResult e PaginatedResult**
  - Origem: assessment C-02, C-03, A-08 | Dep.: nenhuma
  - Ref: [QueryResult.php](../src/Results/QueryResult.php), [PaginatedResult.php](../src/Results/PaginatedResult.php)
  - DoD:
    - [ ] Extrair lógica compartilhada (`column()`, `firstOrDefault()`, `linq()`, `count()`) via composição ou trait interna
    - [ ] Padronizar convenção de chaves JSON (documentando e mantendo retrocompatibilidade)
    - [ ] Eliminar necessidade de `$count` obrigatório em `QueryResult` calculando automaticamente via `count($rows)`
    - [ ] Testes de serialização e manipulação validados em `QueryResultTest` e `PaginatedResultTest`

- [ ] **T-018 · P3 · Adicionar favicon vetorial e tag de ícone na aplicação demo**
  - Origem: manual | Dep.: nenhuma
  - Ref: [demo/index.php](../demo/index.php), [demo/favicon.svg](../demo/favicon.svg)
  - DoD:
    - [ ] Criar arquivo de favicon vetorial SVG moderno (`demo/favicon.svg`) com as cores de marca (#00A3FF e #FF7A00) e cilindros/dados estilizados
    - [ ] Adicionar tag `<link rel="icon" type="image/svg+xml" href="favicon.svg">` no `<head>` de `demo/index.php`
    - [ ] Validar carregamento correto do favicon na aplicação de demonstração sem erros 404