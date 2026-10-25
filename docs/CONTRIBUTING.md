# 🤝 Guia de Contribuição

Agradecemos pelo seu interesse em contribuir com o DbPHP! Sua ajuda torna esta biblioteca ainda melhor e mais robusta.

## 📌 Como Contribuir

1. **Faça um Fork** do repositório
2. **Crie uma nova branch** para sua correção ou recurso:

   ```bash
   git checkout -b feature/<nome-da-feature>
   ```

3. **Faça o commit das alterações** seguindo a convenção de [Conventional Commits](https://www.conventionalcommits.org/pt-br/v1.0.0/) e Gitmoji
4. **Envie para o seu fork** e abra um Pull Request

## 📖 Regras de Contribuição

* Siga o padrão de formatação e codificação PSR-12 e recursos do PHP 8.2+.
* Zero dependências em produção: use apenas recursos nativos do PHP.
* Mantenha as mensagens de commit claras, descritivas e padronizadas.
* Adicione ou atualize os testes unitários (`PHPUnit`) para cobrir suas mudanças.
* Execute a suíte de validação antes de submeter o PR:
  ```bash
  composer check
  ```
* Se adicionar novos métodos ou alterar comportamentos, atualize a documentação no `README.md`.

## 📦 Scripts do Projeto

* `composer format:test` — Verifica a conformidade de formatação com Laravel Pint (PSR-12).
* `composer format:fix` — Aplica correções automáticas de formatação com Laravel Pint.
* `composer lint` — Executa a análise estática formal de código com PHPStan.
* `composer test` — Executa a suíte de testes unitários com PHPUnit.
* `composer check` — Executa a validação completa da suíte (formatação, lint e testes).

## 📝 Formato de Mensagens de Commit

Os commits devem seguir o padrão Conventional Commits com Gitmoji compatível:

```text
<tipo>(escopo opcional): <gitmoji> <descrição no infinitivo>

<corpo opcional>
```

### Tipos Comuns:
* **feat**: ✨ Nova funcionalidade ou recurso
* **fix**: 🐛 Correção de bug
* **docs**: 📝 Alteração em documentações
* **refactor**: ♻️ Refatoração de código sem alteração de comportamento externo
* **perf**: ⚡ Mudança de código que melhora a performance
* **test**: 🧪 Adição ou ajuste de testes
* **ci**: 👷 Alterações em arquivos e scripts de CI/CD
* **chore**: 🔧 Tarefas de manutenção ou configuração do repositório

## 📑 Licença

Ao contribuir, você concorda que suas contribuições serão licenciadas sob a [Licença MIT](../LICENSE) do projeto.

Muito obrigado por ajudar a evoluir o DbPHP! 🚀
