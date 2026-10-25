# Backlog do Projeto: heliomarpm/db-php

> **Meta:** Construção da biblioteca leve de banco de dados db-php com PDO, MySQLi e interop com linq-php\
> **Status:** em desenvolvimento | **Progresso:** 5/7 (71%) | **Atualizado em:** 2026-10-24

## Concluídas recentemente

- [x] **T-001 · P1 · Setup do Repositório, Composer, Pint, PHPStan e Git** (2026-10-24)
- [x] **T-002 · P1 · Configuração Imutável e Hierarquia de Exceções Sem HTML** (2026-10-24)
- [x] **T-003 · P1 · QueryResult com Telemetria e Ponte Fluente para LinqPHP** (2026-10-24)
- [x] **T-004 · P1 · Implementação e Testes do PdoDriver (MySQL e SQLite)** (2026-10-24)
- [x] **T-005 · P1 · Implementação do MysqliDriver com Inferência de Tipos** (2026-10-24)

## Em progresso

- [ ] **T-006 · P1 · Fachada DB e Gerenciador Multi-Conexão com Testes**
  - Critério de Aceite: Classe estática `DB` com suporte a múltiplas conexões nomeadas, atalhos estáticos e factory fluente para conexões rápidas.

## Backlog

- [ ] **T-007 · P1 · Documentação (README), Playground Interativo (Demo) e GitHub Actions**
  - Critério de Aceite: `README.md` com badges e exemplos, pasta `demo/` com mini-app (`composer start`) e pipeline `.github/workflows/ci.yml`.