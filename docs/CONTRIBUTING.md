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

* Siga o padrão de formatação e codificação PSR-12 e recursos do PHP 8.1+.
* Zero dependências em produção: use apenas recursos nativos do PHP.
* Mantenha as mensagens de commit claras, descritivas e padronizadas.
* Adicione ou atualize os testes unitários (`PHPUnit`) para cobrir suas mudanças.
* Execute a suíte de validação antes de submeter o PR:
  ```bash
  composer test
  composer phpstan
  composer bench
  ```
* Se adicionar novos métodos ou alterar comportamentos, atualize a documentação no `README.md`.

## 📦 Scripts do Projeto

* `composer test` — Executa a suíte de testes unitários com PHPUnit.
* `composer bench` — Roda a suíte customizada de benchmarks de desempenho.
* `composer phpstan` — Executa a análise estática formal de código.
* `composer qa` — Executa a bateria completa de validação de qualidade (testes, benchmarks e análise estática).

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

Muito obrigado por ajudar a evoluir o LinqPHP! 🚀
