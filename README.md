<div id="top" align="center">
<h1>
  <img src="./logo.png" alt="DbPHP" width="228" />

  <a href="https://navto.me/heliomarpm" target="_blank"><img src="https://navto.me/assets/navigatetome-brand.png" width="32"/></a>
  ![CI Status](https://github.com/heliomarpm/db-php/actions/workflows/ci.yml/badge.svg?branch=main)
  [![CodeFactor][url-codefactor-badge]][url-codefactor]
  [![Packagist Version][url-packagist-badge]][url-packagist]
  [![Packagist Downloads][url-packagist-downloads-badge]][url-packagist]
</h1>

<div class="badges">

  [![GitHub Sponsors][url-github-sponsors-badge]][url-github-sponsors]
  [![PayPal][url-paypal-badge]][url-paypal]
  [![Ko-fi][url-kofi-badge]][url-kofi]
  [![Liberapay][url-liberapay-badge]][url-liberapay]

</div>
</div>

**DbPHP** é uma biblioteca leve, de alta performance e sem dependências externas para conexão e manipulação de banco de dados (PDO e MySQLi) em PHP.

Inspirada no padrão ergonômico de drivers do OpenCart, porém totalmente modernizada para **PHP 8.2+**, com tipagem estrita, DTOs imutáveis, prepared statements seguros e telemetria nativa de execução.

> [Demo interativo](https://db-php.vercel.app/)

## Requisitos
- PHP 8.2 ou superior
- Extensões `pdo` (com drivers como `pdo_mysql`, `pdo_sqlite`) e/ou `mysqli`

## Instalação
```bash
composer require heliomarpm/db-php
```

## Como Usar

### 1. Inicialização e Conexão Rápida
```php
use HeliomarPM\DbPHP\DB;

// SQLite em memória ou arquivo:
$db = DB::sqlite();

// MySQL via PDO:
$db = DB::mysql(database: "minha_base", username: "root", password: "secret_password");

// MySQL nativo via Driver MySQLi:
$db = DB::connect([
    "driver"   => "mysqli",
    "hostname" => "127.0.0.1",
    "database" => "minha_base",
    "username" => "root",
    "password" => "secret_password",
]);
```

### 2. Consultas com Telemetria Embutida (`QueryResult`)
```php
$result = DB::query("SELECT * FROM produtos WHERE preco > ?", [100.0]);

echo $result->count;       // Quantidade de linhas afetadas/retornadas
echo $result->elapsedTime; // Tempo de execução em segundos com precisão (ex: 0.0012s)
print_r($result->row);     // Primeira linha como array associativo
print_r($result->rows);    // Todas as linhas

// Obter uma única coluna como lista simples:
$nomes = $result->column("nome");
```

### 3. Integração Fluida com `heliomarpm/linq-php`
Se você possui a biblioteca parceira [`heliomarpm/linq-php`](https://github.com/heliomarpm/linq-php) instalada, você pode encadear operações LINQ diretamente do resultado da query:
```php
$destaques = DB::query("SELECT * FROM produtos")
    ->linq()
    ->where(fn($p) => $p["em_estoque"] > 0)
    ->orderByKey("preco", descending: true)
    ->take(5)
    ->toArray();
```

### 4. Transações Atômicas Seguras
```php
DB::transaction(function ($db) {
    $db->execute("UPDATE contas SET saldo = saldo - 50 WHERE id = 1");
    $db->execute("UPDATE contas SET saldo = saldo + 50 WHERE id = 2");
}); // Auto-commit se concluído com sucesso, auto-rollback se qualquer exceção for lançada.
```

## 🤝 Contribuições e suporte

Existem várias formas de contribuir:

- ⭐ Marcar o repositório com uma estrela
- 🐞 Relatar bugs
- 💡 Sugerir funcionalidades ou novos drivers
- 🧾 Melhorar a documentação
- 📢 Compartilhar o projeto

Para propor uma melhoria ou contribuição, veja o [Guia de Contribuição](https://github.com/heliomarpm/db-php/blob/main/docs/CONTRIBUTING.md).\
Se precisar de ajuda ou encontrou um problema, consulte o [Suporte](https://github.com/heliomarpm/db-php/blob/main/docs/SUPPORT.md) ou abra uma [Issue](https://github.com/heliomarpm/db-php/issues). Leia também o [Código de Conduta](https://github.com/heliomarpm/db-php/blob/main/docs/CODE_OF_CONDUCT.md).

Obrigado a todos que já contribuíram:

[![Contribuidores](https://contrib.nn.ci/api?repo=heliomarpm/db-php&no_bot=true)](https://github.com/heliomarpm/db-php/graphs/contributors)

###### Criado com [contrib.nn](https://contrib.nn.ci/?repo=heliomarpm/db-php&no_bot=true)

## 💵 Apoie o Projeto

Se você gosta do projeto, considere fazer uma doação ao desenvolvedor via GitHub Sponsors, Ko-fi, PayPal ou Liberapay — a escolha é sua. 😉

[![GitHub Sponsors][url-github-sponsors-badge]][url-github-sponsors]
[![PayPal][url-paypal-badge]][url-paypal]
[![Ko-fi][url-kofi-badge]][url-kofi]
[![Liberapay][url-liberapay-badge]][url-liberapay]

## 📝 Licença

Distribuído sob a licença [MIT](https://github.com/heliomarpm/db-php/blob/main/LICENSE) © [Heliomar P. Marques](https://github.com/heliomarpm).\
Sinta-se livre para utilizar, customizar e estender estas habilidades nos seus ambientes de desenvolvimento. [🔝](#top)

---
<!-- Sponsor badges -->
[url-github-sponsors]: https://github.com/sponsors/heliomarpm
[url-github-sponsors-badge]: https://img.shields.io/badge/GitHub%20-Sponsor-1C1E26?style=for-the-badge&labelColor=1C1E26&color=db61a2
[url-kofi]: https://ko-fi.com/heliomarpm
[url-kofi-badge]: https://img.shields.io/badge/kofi-1C1E26?style=for-the-badge&labelColor=1C1E26&color=ff5f5f
[url-liberapay]: https://liberapay.com/heliomarpm
[url-liberapay-badge]: https://img.shields.io/badge/liberapay-1C1E26?style=for-the-badge&labelColor=1C1E26&color=f6c915
[url-paypal]: https://bit.ly/paypal-sponsor-heliomarpm
[url-paypal-badge]: https://img.shields.io/badge/donate%20on-paypal-1C1E26?style=for-the-badge&labelColor=1C1E26&color=0475fe

<!-- Packagist badges -->
[url-packagist]: https://packagist.org/packages/heliomarpm/db-php
[url-packagist-badge]: https://img.shields.io/packagist/v/heliomarpm/db-php
[url-packagist-downloads-badge]: https://img.shields.io/packagist/dt/heliomarpm/db-php

<!-- other badges -->
[url-codefactor]: https://www.codefactor.io/repository/github/heliomarpm/db-php
[url-codefactor-badge]: https://www.codefactor.io/repository/github/heliomarpm/db-php/badge