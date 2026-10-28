# 📦 Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado no [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).


# [1.1.0](https://github.com/heliomarpm/db-php/compare/v1.0.0...v1.1.0) (2026-10-28)


### Bug Fixes

* **core:** 🐛 corrigir detecção de SQLite e parâmetros de conexão ([7a3067e](https://github.com/heliomarpm/db-php/commit/7a3067ed7322c9d550d95059afde6d1a778184cc))
* **demo:** 🐛 corrigir sincronização e resiliência de filtros na paginação ([fb62a65](https://github.com/heliomarpm/db-php/commit/fb62a6513f1289f0b2a80471c29b4ef4192d4218))
* **drivers:** 🐛 desacoplar auto-rollback interno de erros de query ([84a3f0f](https://github.com/heliomarpm/db-php/commit/84a3f0fc2310d1c131ce216d1ef2fd7d6aa132c5))
* **mysqli:** 🐛 corrigir inferência e bind de booleanos e nulos no MysqliDriver ([4a5299f](https://github.com/heliomarpm/db-php/commit/4a5299fd7bb8f5f1944165f667b2c57c37fc4c79))


### Features

* **debug:** ✨ adicionar modo debug agregador de métricas e consultas ([af460a9](https://github.com/heliomarpm/db-php/commit/af460a9f57f97c0aa33fa5ae4c4afdd1fe633cde))
* **demo:** ✨ adicionar exemplo interativo de controle manual de transações ([e0f5801](https://github.com/heliomarpm/db-php/commit/e0f5801f07c415df753f37ee431bf415b95a06c0))
* **demo:** ✨ adicionar favicon SVG à página interativa ([4ba5696](https://github.com/heliomarpm/db-php/commit/4ba56962b0e5c9366883caaf93959a4741cf7d91))
* **demo:** 🔒 blindar sandbox do playground contra rce e comandos sql ([e86b659](https://github.com/heliomarpm/db-php/commit/e86b65906b7c5588829980d33fa16b0d3b029e3c))
* **mysqli:** ✨ implementar parser léxico seguro para parâmetros nomeados ([a2383dc](https://github.com/heliomarpm/db-php/commit/a2383dc16cc944a3a47cdf228c4e338dc84b1b57))


### Performance Improvements

* **pagination:** ⚡ remover ORDER BY da subquery de contagem ([8e6364d](https://github.com/heliomarpm/db-php/commit/8e6364d62168fec269aa9decea934d8cfa902166))

# 1.0.0 (2026-10-25)


### Bug Fixes

* 🐛 remover parametro driver inexistente no demo do QueryResult ([29e5378](https://github.com/heliomarpm/db-php/commit/29e5378559bd6dcbf85bce32da7f68e348e0ab0f))


### Features

* ✨ implementar demo interativo com telemetria e suporte ao vercel ([fd27d3c](https://github.com/heliomarpm/db-php/commit/fd27d3c37d633f8408411914a902f9a75563de51))
* ✨ implementar paginacao nativa com DTO PaginatedResult ([4b0c642](https://github.com/heliomarpm/db-php/commit/4b0c64227fe5c4666aeaa8313a713dee4e6919bf))
* ✨ inicializar repositorio db-php com arquitetura, spec e testes ([f7bfb41](https://github.com/heliomarpm/db-php/commit/f7bfb41896d1bce63ef9b0a15286271f99ba6879))
* **demo:** ✨ adicionar reatividade com Vue.js 3 via CDN na paginacao ([ddbd02b](https://github.com/heliomarpm/db-php/commit/ddbd02b2bbcd99399a516a017512ee149937db54))
