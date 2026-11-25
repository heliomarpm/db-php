---
trigger: always_on
description: Convenção de commits da aplicação demo para evitar releases desnecessárias
---

# Regra de Commits da Aplicação Demo (`demo/`)

- Para não poluir o CHANGELOG, Todo commit que altere **exclusivamente** arquivos da aplicação de demonstração ou deploy da demo (`demo/**`, `api/**`, `vercel.json`, `.vercelignore`) **DEVE** usar o tipo `chore(demo): ...` (por exemplo: `chore(demo): ✨ ...` ou `chore(demo): 🐛 ...`).
- **Nunca** utilize `feat` ou `fix` em commits puramente da demo, pois o Semantic Release gera automaticamente uma nova release/tag da biblioteca `heliomarpm/db-php` para commits `feat` e `fix`.
- Quando um commit alterar código da biblioteca em `src/` (mesmo que também atualize `demo/`), utilize normalmente o tipo semântico correspondente (`feat`, `fix`, `perf`, `refactor`).
