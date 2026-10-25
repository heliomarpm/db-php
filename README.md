# DbPHP

Uma biblioteca leve, de alta performance e sem dependências externas para conexão e manipulação de banco de dados (PDO e MySQLi) em PHP.

Inspirada no padrão ergonômico de drivers do OpenCart, porém totalmente modernizada para **PHP 8.2+**, com tipagem estrita, DTOs imutáveis, prepared statements seguros e telemetria nativa.

## Requisitos
- PHP 8.2 ou superior
- Extensões `pdo` e/ou `mysqli`

## Instalação
```bash
composer require heliomarpm/db-php
```

## Como Usar

### 1. Inicialização e Conexão Rápida
```php
use HeliomarPM\DbPHP\DB;

// SQLite em memória:
$db = DB::sqlite();

// MySQL:
$db = DB::mysql(database: "minha_base", username: "root", password: "secret_password");
```

### 2. Consultas com Telemetria Embutida (`QueryResult`)
```php
$result = DB::query("SELECT * FROM produtos WHERE preco > ?", [100.0]);

echo $result->count;       // 15 linhas
echo $result->elapsedTime; // 0.0015 segundos
print_r($result->row);     // Primeira linha como array associativo
print_r($result->rows);    // Todas as linhas

// Obter uma única coluna como lista:
$nomes = $result->column('nome');
```

### 3. Integração Direta com `heliomarpm/linq-php`
Se você possui o pacote `heliomarpm/linq-php` instalado, pode encadear métodos LINQ direto do resultado:
```php
$destaques = DB::query("SELECT * FROM produtos")
    ->linq()
    ->where(fn($p) => $p['em_estoque'] > 0)
    ->orderByKey('preco', descending: true)
    ->take(5)
    ->toArray();
```

### 4. Transações Atômicas
```php
DB::transaction(function ($db) {
    $db->execute("UPDATE contas SET saldo = saldo - 50 WHERE id = 1");
    $db->execute("UPDATE contas SET saldo = saldo + 50 WHERE id = 2");
}); // Auto-commit se sucesso, auto-rollback em caso de exceção.
```