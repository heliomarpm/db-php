<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/seed.php';


// Inicializa a base de demonstração isolada para esta requisição
$db = createDemoDatabase();

// =========================================================================
// ENDPOINT JSON REATIVO (API REST para Vue.js)
// =========================================================================
if (isset($_GET['api']) && $_GET['api'] === 'paginate') {
    header('Content-Type: application/json; charset=utf-8');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 4)));
    $minPreco = (float)($_GET['min_preco'] ?? 0.0);
    $search = trim((string)($_GET['q'] ?? ''));

    $sql = "SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
            FROM produtos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.preco >= :min_preco";
    $params = ["min_preco" => $minPreco];

    if ($search !== "") {
        $sql .= " AND (p.nome_produto LIKE :search OR c.nome_categoria LIKE :search)";
        $params["search"] = "%{$search}%";
    }

    $sql .= " ORDER BY p.id_produto ASC";

    $paginado = $db->paginate($sql, $params, $page, $perPage);
    echo json_encode($paginado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// DEFINIÇÃO DOS CENÁRIOS INTERATIVOS
// =========================================================================
$cenarios = [
    'telemetria' => [
        'titulo' => 'Telemetria & Alta Performance',
        'tag'    => 'TELEMETRIA',
        'badge'  => 'bg-primary',
        'desc'   => 'Executa consulta com prepared statements e captura contagem e tempo de execução exato em segundos sem overhead de ORM.',
        'codigo' => <<<'CODE'
// Consulta produtos acima de R$ 90,00 com prepared statements:
$result = DB::query("
    SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
    FROM produtos p
    INNER JOIN categorias c ON c.id_categoria = p.id_categoria
    WHERE p.preco >= ?
    ORDER BY p.preco DESC
", [90.00]);

// Métricas de telemetria automáticas:
$tempo = $result->elapsedTime; // ex: 0.000180s
$total = $result->count;       // ex: 7 registros
$linhas = $result->rows;
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            return $db->query("
                SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
                FROM produtos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.preco >= ?
                ORDER BY p.preco DESC
            ", [90.00]);
        }
    ],

    'parametros_nomeados' => [
        'titulo' => 'Parâmetros Nomeados (:param)',
        'tag'    => 'NAMED PARAMS',
        'badge'  => 'bg-info',
        'desc'   => 'Demonstra prepared statements com múltiplos parâmetros nomeados em array associativo (:min_preco, :min_estoque).',
        'codigo' => <<<'CODE'
// Consulta usando parâmetros nomeados em array associativo:
$result = DB::query("
    SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
    FROM produtos p
    INNER JOIN categorias c ON c.id_categoria = p.id_categoria
    WHERE p.preco >= :min_preco AND p.estoque >= :min_estoque
    ORDER BY p.preco DESC
", [
    "min_preco"   => 100.00,
    "min_estoque" => 10,
]);

// O DbPHP aceita tanto ['min_preco' => 100.0] quanto [':min_preco' => 100.0]
$tempo = $result->elapsedTime;
$total = $result->count;
$linhas = $result->rows;
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            return $db->query("
                SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
                FROM produtos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.preco >= :min_preco AND p.estoque >= :min_estoque
                ORDER BY p.preco DESC
            ", [
                "min_preco"   => 100.00,
                "min_estoque" => 10,
            ]);
        }
    ],
    'extracao_coluna' => [
        'titulo' => 'Extração de Coluna Direta ($result->column)',
        'tag'    => 'ERGONOMIA',
        'badge'  => 'bg-info',
        'desc'   => 'Elimina o boilerplate de array_column() para extrair listas lineares simples de dados diretamente do resultado.',
        'codigo' => <<<'CODE'
// Extrai apenas os nomes dos clientes do estado de Minas Gerais:
$result = DB::query("
    SELECT nome_cliente, uf, saldo_credito
    FROM clientes
    WHERE uf = ?
    ORDER BY nome_cliente ASC
", ['MG']);

// Extração direta sem loops:
$nomes = $result->column('nome_cliente');
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $res = $db->query("
                SELECT nome_cliente, uf, saldo_credito
                FROM clientes
                WHERE uf = ?
                ORDER BY nome_cliente ASC
            ", ['MG']);

            // Monta objeto com as colunas para exibição na tabela
            $nomes = $res->column('nome_cliente');
            $data = array_map(fn ($nome, $i) => [
                'posicao' => $i + 1,
                'nome_cliente' => $nome,
                'estado' => 'MG',
                'metodo' => '$result->column("nome_cliente")'
            ], $nomes, array_keys($nomes));

            return new \HeliomarPM\DbPHP\Results\QueryResult(
                rows: $data,
                count: count($data),
                elapsedTime: $res->elapsedTime,
            );
        }
    ],

    'paginacao' => [
        'titulo' => 'Paginação Nativa (PaginatedResult)',
        'tag'    => 'PAGINATION',
        'badge'  => 'bg-success',
        'desc'   => 'Executa consulta paginada com contagem automática (COUNT) e injeção de LIMIT/OFFSET, retornando metadados completos de paginação.',
        'codigo' => <<<'CODE'
$pagina = (int)($_GET['page'] ?? 1);
$porPagina = 4;

// Paginação automática com parâmetros nomeados:
$paginado = DB::paginate(
    sql: "SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
          FROM produtos p
          INNER JOIN categorias c ON c.id_categoria = p.id_categoria
          WHERE p.preco > :preco
          ORDER BY p.id_produto ASC",
    params: ["preco" => 50.00],
    page: $pagina,
    perPage: $porPagina
);

// Metadados ricos disponíveis:
$itens       = $paginado->items;           // 4 itens desta página
$total       = $paginado->total;           // 9 produtos no total
$paginaAtual = $paginado->currentPage;     // 1, 2 ou 3
$ultimaPagina= $paginado->lastPage;        // 3
$faixa       = "De {$paginado->from} até {$paginado->to}";
$temMais     = $paginado->hasMorePages;    // true se houver próxima
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $pagina = (int)($_GET['page'] ?? 1);
            return $db->paginate(
                "SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
                 FROM produtos p
                 INNER JOIN categorias c ON c.id_categoria = p.id_categoria
                 WHERE p.preco > :preco
                 ORDER BY p.id_produto ASC",
                ["preco" => 50.00],
                page: $pagina,
                perPage: 4
            );
        }
    ],
    'join_relacional' => [
        'titulo' => 'Relatório Relacional com Múltiplos JOINs',
        'tag'    => 'RELATIONAL JOIN',
        'badge'  => 'bg-success',
        'desc'   => 'Combina 4 tabelas relacionais (Pedidos, Clientes, Itens e Produtos) com cálculo de subtotal por linha em SQL nativo.',
        'codigo' => <<<'CODE'
$result = DB::query("
    SELECT
        p.id_pedido,
        c.nome_cliente,
        c.uf,
        pr.nome_produto,
        i.qtd,
        i.preco_unitario,
        ROUND(i.qtd * i.preco_unitario, 2) AS subtotal,
        p.status
    FROM pedidos p
    INNER JOIN clientes c ON c.id_cliente = p.id_cliente
    INNER JOIN itens_pedido i ON i.id_pedido = p.id_pedido
    INNER JOIN produtos pr ON pr.id_produto = i.id_produto
    ORDER BY p.id_pedido ASC, subtotal DESC
");
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            return $db->query("
                SELECT
                    p.id_pedido,
                    c.nome_cliente,
                    c.uf,
                    pr.nome_produto,
                    i.qtd,
                    i.preco_unitario,
                    ROUND(i.qtd * i.preco_unitario, 2) AS subtotal,
                    p.status
                FROM pedidos p
                INNER JOIN clientes c ON c.id_cliente = p.id_cliente
                INNER JOIN itens_pedido i ON i.id_pedido = p.id_pedido
                INNER JOIN produtos pr ON pr.id_produto = i.id_produto
                ORDER BY p.id_pedido ASC, subtotal DESC
            ");
        }
    ],

    'db_plus_linq' => [
        'titulo' => 'Poder Combinado: DbPHP + LinqPHP',
        'tag'    => 'DB + LINQ',
        'badge'  => 'bg-warning text-dark',
        'desc'   => 'Executa a consulta com DbPHP e encadeia operações LINQ em memória diretamente via ->linq() com total interoperabilidade.',
        'codigo' => <<<'CODE'
// 1. Busca os dados brutos no banco via DbPHP
// 2. Encadedeia transformações LINQ com LinqPHP:
$itensTransformados = DB::query("
    SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
    FROM produtos p
    INNER JOIN categorias c ON c.id_categoria = p.id_categoria
")
->linq()
->where(fn($p) => $p['preco'] > 50.00)
->orderByKey('preco', descending: true)
->select([
    'id_produto'     => 'Código',
    'nome_produto'   => 'Descrição',
    'nome_categoria' => 'Categoria',
    'preco'          => 'Valor Unitário',
    'estoque'        => 'Estoque Atual'
])
->take(5)
->toArray();
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $start = microtime(true);
            $queryResult = $db->query("
                SELECT p.id_produto, p.nome_produto, c.nome_categoria, p.preco, p.estoque
                FROM produtos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            ");

            if (class_exists(\HeliomarPM\LinqPHP\LinqPHP::class)) {
                $rows = $queryResult->linq()
                    ->where(fn ($p) => (float)$p['preco'] > 50.00)
                    ->orderByKey('preco', descending: true)
                    ->select([
                        'id_produto'     => 'Código',
                        'nome_produto'   => 'Descrição',
                        'nome_categoria' => 'Categoria',
                        'preco'          => 'Valor Unitário',
                        'estoque'        => 'Estoque Atual'
                    ])
                    ->take(5)
                    ->toArray();
            } else {
                $rows = array_slice($queryResult->rows, 0, 5);
            }

            $elapsed = microtime(true) - $start;

            return new \HeliomarPM\DbPHP\Results\QueryResult(
                rows: $rows,
                count: count($rows),
                elapsedTime: $elapsed,
            );
        }
    ],

    'transacao_commit' => [
        'titulo' => 'Transação Atômica: Transferência (Commit)',
        'tag'    => 'TRANSACTION',
        'badge'  => 'bg-danger',
        'desc'   => 'Demonstra DB::transaction() executando débitos e créditos com auto-commit garantido caso nenhuma exceção ocorra.',
        'codigo' => <<<'CODE'
// Transfere R$ 1.500,00 da Matriz para a Filial:
DB::transaction(function ($driver) {
    $driver->execute(
        "UPDATE contas SET saldo = saldo - ? WHERE id_conta = ?",
        [1500.00, 1]
    );
    $driver->execute(
        "UPDATE contas SET saldo = saldo + ? WHERE id_conta = ?",
        [1500.00, 2]
    );
});

// Consulta o saldo atualizado de todas as contas:
$contas = DB::query("SELECT * FROM contas");
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $db->transaction(function ($driver) {
                $driver->execute("UPDATE contas SET saldo = saldo - ? WHERE id_conta = ?", [1500.00, 1]);
                $driver->execute("UPDATE contas SET saldo = saldo + ? WHERE id_conta = ?", [1500.00, 2]);
            });

            return $db->query("SELECT id_conta, titular, saldo, 'Sucesso (Commit)' AS status_transacao FROM contas");
        }
    ],

    'transacao_rollback' => [
        'titulo' => 'Transação Atômica: Falha e Rollback',
        'tag'    => 'AUTO-ROLLBACK',
        'badge'  => 'bg-secondary',
        'desc'   => 'Simula uma exceção no meio de uma transação. O DbPHP intercepta o erro, reverte todas as mudanças (Rollback) e mantém a consistência.',
        'codigo' => <<<'CODE'
try {
    DB::transaction(function ($driver) {
        // Passo 1: Debita R$ 2.000 da Matriz
        $driver->execute("UPDATE contas SET saldo = saldo - ? WHERE id_conta = ?", [2000.00, 1]);

        // Passo 2: Simula erro crítico ou validação de negócio que falha
        throw new \RuntimeException("Falha de comunicação com o Gateway de Pagamentos!");

        // Passo 3: Nunca será executado
        $driver->execute("UPDATE contas SET saldo = saldo + ? WHERE id_conta = ?", [2000.00, 2]);
    });
} catch (\Throwable $e) {
    // Transação revertida com segurança via $pdo->rollBack()!
}

// Saldos permanecem intactos:
$contas = DB::query("SELECT * FROM contas");
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $mensagemErro = '';
            try {
                $db->transaction(function ($driver) {
                    $driver->execute("UPDATE contas SET saldo = saldo - ? WHERE id_conta = ?", [2000.00, 1]);
                    throw new \RuntimeException("Erro intencional: Simulação de falha de conexão na transferência!");
                });
            } catch (\Throwable $e) {
                $mensagemErro = $e->getMessage();
            }

            $res = $db->query("SELECT id_conta, titular, saldo FROM contas");
            $rows = array_map(function ($row) use ($mensagemErro) {
                $row['estado_transacao'] = 'Revertido com Sucesso (Rollback)';
                $row['motivo_reversao'] = $mensagemErro;
                return $row;
            }, $res->rows);

            return new \HeliomarPM\DbPHP\Results\QueryResult(
                rows: $rows,
                count: count($rows),
                elapsedTime: $res->elapsedTime,
            );
        }
    ],

    'playground' => [
        'titulo' => 'SQL Playground Interativo (Livre)',
        'tag'    => 'PLAYGROUND',
        'badge'  => 'bg-dark',
        'desc'   => 'Digite qualquer instrução SELECT no campo abaixo para consultar a base SQLite em memória ao vivo.',
        'codigo' => <<<'CODE'
// Execute qualquer consulta SQL contra as tabelas do demo:
// Tabelas: clientes, categorias, produtos, pedidos, itens_pedido, contas
$result = DB::query($sqlCustomizado);
CODE,
        'run' => function (\HeliomarPM\DbPHP\Contracts\DriverInterface $db) {
            $customSql = trim($_POST['custom_sql'] ?? $_GET['sql'] ?? "SELECT * FROM produtos WHERE estoque < 20");
            if (empty($customSql)) {
                $customSql = "SELECT * FROM produtos WHERE estoque < 20";
            }

            return $db->query($customSql);
        }
    ],
];

// Identifica o cenário ativo
$cenarioAtual = $_GET['cenario'] ?? 'telemetria';
if (!array_key_exists($cenarioAtual, $cenarios)) {
    $cenarioAtual = 'telemetria';
}
$dadosCenario = $cenarios[$cenarioAtual];

// Execução
$erroExecucao = null;
$startMem = memory_get_usage();

try {
    $queryResult = $dadosCenario['run']($db);
    if ($queryResult instanceof \HeliomarPM\DbPHP\Results\PaginatedResult) {
        $linhas = $queryResult->items;
        $totalLinhas = $queryResult->total;
        $tempoExecucao = number_format($queryResult->elapsedTime, 6);
        $tempoMs = number_format($queryResult->elapsedTime * 1000, 3);
    } else {
        $linhas = $queryResult->rows;
        $totalLinhas = $queryResult->count;
        $tempoExecucao = number_format($queryResult->elapsedTime, 6);
        $tempoMs = number_format($queryResult->elapsedTime * 1000, 3);
    }
} catch (\Throwable $e) {
    $erroExecucao = $e->getMessage();
    $linhas = [];
    $totalLinhas = 0;
    $tempoExecucao = '0.000000';
    $tempoMs = '0.000';
}

$memoriaUsada = sprintf('%.2f KB', (memory_get_usage() - $startMem) / 1024);

// Função auxiliar para renderizar a tabela
if (!function_exists('renderGridTable')) {
    function renderGridTable(array $dados): string
    {
        if (empty($dados)) {
            return '<div class="alert alert-secondary text-center py-4 my-2"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Nenhum registro retornado para esta consulta.</div>';
        }

        $colunas = array_keys(reset($dados));
        $html = '<div class="table-responsive border rounded-3 bg-white shadow-sm">';
        $html .= '<table class="table table-hover table-striped align-middle mb-0 font-sans" style="font-size: 0.88rem;">';
        $html .= '<thead class="table-light border-bottom text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;"><tr>';

        foreach ($colunas as $col) {
            $html .= "<th class='py-3 px-3'>" . htmlspecialchars((string)$col) . "</th>";
        }
        $html .= '</tr></thead><tbody>';

        foreach ($dados as $linha) {
            $html .= '<tr>';
            foreach ($colunas as $col) {
                $valor = $linha[$col] ?? null;

                if ($valor === null) {
                    $celula = '<span class="badge bg-secondary-subtle text-secondary font-monospace">null</span>';
                } elseif (is_bool($valor)) {
                    $celula = $valor
                        ? '<span class="badge bg-success-subtle text-success">Sim</span>'
                        : '<span class="badge bg-light text-muted border">Não</span>';
                } elseif (is_numeric($valor) && (str_contains((string)$col, 'preco') || str_contains((string)$col, 'saldo') || str_contains((string)$col, 'subtotal') || str_contains((string)$col, 'Valor'))) {
                    $celula = '<strong>R$ ' . number_format((float)$valor, 2, ',', '.') . '</strong>';
                } elseif ($col === 'status') {
                    $cor = match ($valor) {
                        'concluido' => 'bg-success',
                        'pendente'  => 'bg-warning text-dark',
                        'cancelado' => 'bg-danger',
                        default     => 'bg-secondary'
                    };
                    $celula = "<span class='badge {$cor} text-uppercase' style='font-size: 0.7rem;'>{$valor}</span>";
                } elseif ($col === 'vip') {
                    $celula = ((int)$valor === 1)
                        ? '<span class="badge bg-warning text-dark fw-bold">VIP ⭐</span>'
                        : '<span class="badge bg-light text-muted border">Comum</span>';
                } elseif (is_array($valor)) {
                    $celula = '<code class="small">' . htmlspecialchars(json_encode($valor, JSON_UNESCAPED_UNICODE)) . '</code>';
                } else {
                    $celula = htmlspecialchars((string)$valor);
                }

                $html .= "<td class='py-2 px-3'>{$celula}</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DbPHP · Laboratório Interativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <style>
        :root {
            --brand-cyan: #00A3FF;
            --brand-orange: #FF7A00;
            --brand-dark: #0B0F19;
            --brand-card-bg: #111827;
            --brand-gradient: linear-gradient(135deg, #00A3FF 0%, #0066FF 40%, #FF7A00 100%);
        }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #0d1117;
            color: #333;
        }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .text-cyan { color: var(--brand-cyan) !important; }
        .text-orange { color: var(--brand-orange) !important; }
        .bg-card { background-color: #161b22; border: 1px solid #30363d; }
        .bg-card-subtle { background-color: #21262d; }
        .btn-brand-gradient {
            background: linear-gradient(135deg, #00A3FF, #FF7A00);
            color: white;
            border: none;
            font-weight: 600;
        }
        .btn-brand-gradient:hover {
            opacity: 0.92;
            color: white;
        }
        .scenario-pill {
            text-decoration: none;
            color: #8b949e;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 9999px;
            padding: 0.45rem 0.9rem;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .scenario-pill:hover {
            color: #58a6ff;
            border-color: #58a6ff;
            background: #21262d;
        }
        .scenario-pill.active {
            color: white;
            background: linear-gradient(135deg, #00A3FF, #0066FF);
            border-color: #00A3FF;
            box-shadow: 0 0 15px rgba(0, 163, 255, 0.4);
        }
        .hud-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
        }
        .nav-tabs .nav-link {
            color: #8b949e;
            border: none;
            border-bottom: 2px solid transparent;
            font-weight: 600;
        }
        .nav-tabs .nav-link.active {
            color: #58a6ff;
            background: transparent;
            border-bottom: 2px solid #58a6ff;
        }
        pre[class*="language-"] {
            margin: 0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
        }
        .table {
            color: #555;
            background-color: transparent;
        }
        .table-light {
            background-color: #21262d !important;
            color: #555 !important;
        }
        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: rgba(255, 255, 255, 0.02);
            color: #555;
        }
        .table-hover>tbody>tr:hover>* {
            background-color: rgba(0, 163, 255, 0.08);
            color: #555;
        }
    </style>
</head>
<body class="py-4">
    <div class="container-xl">
        <!-- HEADER -->
        <header class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom border-secondary border-opacity-25">
            <div class="d-flex align-items-center gap-3">
                <a href="https://github.com/heliomarpm/db-php" target="_blank" class="text-decoration-none">
                    <img src="./logo.png" alt="DbPHP" width="54" height="54" class="rounded-3 shadow-sm" onerror="this.style.display='none'">
                </a>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="h3 fw-bold mb-0 text-white font-mono">
                            Db<span class="text-orange">PHP</span>
                        </h1>
                        <span class="badge bg-primary-subtle text-cyan border border-primary border-opacity-25 rounded-pill px-2 py-1 font-mono" style="font-size: 0.72rem;">v1.0.0</span>
                    </div>
                    <p class="text-secondary small mb-0">Laboratório Interativo & Telemetria em Tempo Real (SQLite In-Memory)</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
                <a href="https://github.com/heliomarpm/db-php" target="_blank" class="btn btn-sm btn-outline-secondary text-white px-3">
                    <i class="bi bi-github me-1"></i> GitHub
                </a>
                <a href="https://packagist.org/packages/heliomarpm/db-php" target="_blank" class="btn btn-sm btn-outline-warning px-3">
                    <i class="bi bi-box-seam me-1"></i> Packagist
                </a>
                <a href="https://linq-php.vercel.app/" target="_blank" class="btn btn-sm btn-brand-gradient px-3">
                    <i class="bi bi-lightning-charge-fill me-1"></i> LinqPHP Demo
                </a>
            </div>
        </header>

        <!-- SELETOR DE CENÁRIOS -->
        <section class="mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-secondary small fw-semibold text-uppercase me-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">Cenários:</span>
                <?php foreach ($cenarios as $key => $item): ?>
                    <a href="?cenario=<?= $key ?>" class="scenario-pill <?= ($cenarioAtual === $key) ? 'active' : '' ?>">
                        <span><?= $item['titulo'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- CARDS DE TELEMETRIA (HUD) -->
        <section class="mb-4">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="hud-card">
                        <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.7rem;">Tempo de Execução</div>
                        <div class="h3 fw-bold text-cyan font-mono mb-0 mt-1">
                            <?= $tempoMs ?> <span class="fs-6 fw-normal text-secondary">ms</span>
                        </div>
                        <div class="text-secondary small font-mono mt-1" style="font-size: 0.72rem;">
                            <?= $tempoExecucao ?> s
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="hud-card">
                        <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.7rem;">Registros Retornados</div>
                        <div class="h3 fw-bold text-orange font-mono mb-0 mt-1">
                            <?= $totalLinhas ?>
                        </div>
                        <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                            $result->count
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="hud-card">
                        <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.7rem;">Driver Ativo</div>
                        <div class="h5 fw-bold text-white font-mono mb-0 mt-1">
                            SQLite PDO
                        </div>
                        <div class="text-success small mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-hdd-network-fill me-1"></i> :memory: (Zero overhead)
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="hud-card">
                        <div class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.7rem;">Memória da Query</div>
                        <div class="h5 fw-bold text-white font-mono mb-0 mt-1">
                            <?= $memoriaUsada ?>
                        </div>
                        <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                            Peak isolado da query
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DESCRIÇÃO DO CENÁRIO ATIVO -->
        <div class="bg-card p-3 rounded-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge <?= $dadosCenario['badge'] ?> text-uppercase mb-2" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                    <?= $dadosCenario['tag'] ?>
                </span>
                <h2 class="h5 fw-bold text-white mb-1"><?= $dadosCenario['titulo'] ?></h2>
                <p class="text-secondary small mb-0"><?= $dadosCenario['desc'] ?></p>
            </div>
            <?php if ($cenarioAtual === 'playground'): ?>
                <button type="submit" form="sqlPlaygroundForm" class="btn btn-brand-gradient btn-sm px-4">
                    <i class="bi bi-play-fill me-1"></i> Executar SQL
                </button>
            <?php endif; ?>
        </div>

        <?php if ($erroExecucao): ?>
            <div class="alert alert-danger bg-danger-subtle text-danger border-danger mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Erro na Execução:</strong> <?= htmlspecialchars($erroExecucao) ?>
            </div>
        <?php endif; ?>

        <?php if ($cenarioAtual === 'playground'): ?>
            <!-- EDITOR DE SQL LIVRE -->
            <div class="bg-card p-3 rounded-3 mb-4">
                <form id="sqlPlaygroundForm" method="POST" action="?cenario=playground">
                    <label class="form-label small fw-semibold text-secondary text-uppercase font-mono">
                        <i class="bi bi-terminal me-1"></i> Editor SQL Livre (SQLite)
                    </label>
                    <textarea name="custom_sql" rows="3" class="form-control font-mono bg-dark text-white border-secondary mb-2" style="font-size: 0.9rem;" placeholder="Ex: SELECT * FROM produtos WHERE preco > 100"><?= htmlspecialchars($_POST['custom_sql'] ?? $_GET['sql'] ?? "SELECT * FROM produtos WHERE estoque < 20") ?></textarea>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Tabelas disponíveis: <code>clientes</code>, <code>categorias</code>, <code>produtos</code>, <code>pedidos</code>, <code>itens_pedido</code>, <code>contas</code></span>
                        <button type="submit" class="btn btn-brand-gradient btn-sm px-3">
                            <i class="bi bi-play-circle-fill me-1"></i> Rodar Consulta
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- TABS DE VISUALIZAÇÃO -->
        <div class="bg-card rounded-3 p-3">
            <ul class="nav nav-tabs border-secondary border-opacity-25 mb-3" id="demoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="grid-tab" data-bs-toggle="tab" data-bs-target="#grid-pane" type="button" role="tab">
                        <i class="bi bi-table me-1"></i> Tabela Interativa (<?= $totalLinhas ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="code-tab" data-bs-toggle="tab" data-bs-target="#code-pane" type="button" role="tab">
                        <i class="bi bi-code-slash me-1"></i> Código PHP
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="json-tab" data-bs-toggle="tab" data-bs-target="#json-pane" type="button" role="tab">
                        <i class="bi bi-braces me-1"></i> JSON Bruto
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="schema-tab" data-bs-toggle="tab" data-bs-target="#schema-pane" type="button" role="tab">
                        <i class="bi bi-database me-1"></i> Schema das Tabelas
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="demoTabsContent">
                                <!-- TAB 1: GRID TABELA -->
                <div class="tab-pane fade show active" id="grid-pane" role="tabpanel">
                    <?php if ($cenarioAtual === 'paginacao'): ?>
                        <!-- APLICAÇÃO REATIVA VUE.JS 3 VIA CDN -->
                        <div id="vue-pagination-app">
                            <!-- Painel de Controles Reativos -->
                            <div class="p-3 mb-3 rounded-3 bg-card-subtle border border-secondary border-opacity-25 shadow-sm">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 font-mono px-2 py-1">
                                            <i class="bi bi-lightning-charge-fill me-1"></i>Vue 3 Reativo via CDN
                                        </span>
                                        <span class="text-secondary small">Paginação, filtros e telemetria sem recarregar a página</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="text-secondary small font-mono">
                                            Tempo API: <strong class="text-cyan">{{ responseTimeMs }} ms</strong>
                                        </span>
                                        <div v-if="loading" class="spinner-border spinner-border-sm text-cyan" role="status"></div>
                                    </div>
                                </div>

                                <div class="row g-3 align-items-end">
                                    <!-- Busca Instantânea -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label small text-secondary text-uppercase font-mono mb-1">
                                            <i class="bi bi-search me-1"></i> Filtrar Catálogo
                                        </label>
                                        <input type="text" v-model="search" @input="debouncedFetch" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Buscar por produto ou categoria...">
                                    </div>

                                    <!-- Slider Preço Mínimo -->
                                    <div class="col-12 col-md-5">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label small text-secondary text-uppercase font-mono mb-0">
                                                <i class="bi bi-tag-fill me-1"></i> Preço Mínimo
                                            </label>
                                            <span class="badge bg-primary font-mono">R$ {{ minPreco.toFixed(2) }}</span>
                                        </div>
                                        <input type="range" v-model.number="minPreco" @input="debouncedFetch" min="0" max="3500" step="50" class="form-range">
                                    </div>

                                    <!-- Seletor de Itens por Página -->
                                    <div class="col-12 col-md-3">
                                        <label class="form-label small text-secondary text-uppercase font-mono mb-1">
                                            <i class="bi bi-list-ol me-1"></i> Itens por Página
                                        </label>
                                        <select v-model.number="perPage" @change="fetchPage(1)" class="form-select form-select-sm bg-dark text-white border-secondary">
                                            <option :value="2">2 por página</option>
                                            <option :value="4">4 por página</option>
                                            <option :value="6">6 por página</option>
                                            <option :value="10">10 por página</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabela Reativa -->
                            <div class="table-responsive border rounded-3 bg-white shadow-sm position-relative">
                                <div v-if="loading" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-75" style="z-index: 10;">
                                    <div class="spinner-border text-cyan" role="status"></div>
                                </div>

                                <table class="table table-hover table-striped align-middle mb-0 font-sans" style="font-size: 0.88rem;">
                                    <thead class="table-light border-bottom text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                                        <tr>
                                            <th class="py-3 px-3">Código</th>
                                            <th class="py-3 px-3">Produto</th>
                                            <th class="py-3 px-3">Categoria</th>
                                            <th class="py-3 px-3">Preço Unitário</th>
                                            <th class="py-3 px-3">Estoque</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="items.length === 0">
                                            <td colspan="5" class="text-center py-4 text-secondary">
                                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                                Nenhum produto atende aos filtros atuais.
                                            </td>
                                        </tr>
                                        <tr v-for="item in items" :key="item.id_produto">
                                            <td class="py-2 px-3 font-mono text-primary">#{{ item.id_produto }}</td>
                                            <td class="py-2 px-3 fw-semibold">{{ item.nome_produto }}</td>
                                            <td class="py-2 px-3">
                                                <span class="badge bg-primary-subtle text-cyan border border-primary border-opacity-25">{{ item.nome_categoria }}</span>
                                            </td>
                                            <td class="py-2 px-3 font-mono">
                                                <strong>R$ {{ Number(item.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
                                            </td>
                                            <td class="py-2 px-3">
                                                <span :class="item.estoque <= 10 ? 'badge bg-danger-subtle text-danger border border-danger' : 'badge bg-success-subtle text-success border border-success'">
                                                    {{ item.estoque }} un
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Barra de Paginação Reativa -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between p-3 mt-3 bg-card-subtle rounded-3 border border-secondary border-opacity-25">
                                <div class="text-secondary small font-mono">
                                    Exibindo <strong>{{ pagination.from || 0 }}-{{ pagination.to || 0 }}</strong> de <strong>{{ pagination.total || 0 }}</strong> registros (Página {{ pagination.current_page || 1 }} de {{ pagination.last_page || 1 }})
                                </div>
                                <div class="btn-group btn-group-sm mt-2 mt-md-0" role="group">
                                    <button @click="fetchPage(pagination.current_page - 1)" :disabled="!pagination.has_previous" class="btn btn-outline-secondary">
                                        « Anterior
                                    </button>
                                    <button v-for="p in pagination.last_page" :key="p" @click="fetchPage(p)" class="btn" :class="p === pagination.current_page ? 'btn-primary' : 'btn-outline-secondary'">
                                        {{ p }}
                                    </button>
                                    <button @click="fetchPage(pagination.current_page + 1)" :disabled="!pagination.has_more" class="btn btn-outline-secondary">
                                        Próxima »
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?= renderGridTable($linhas) ?>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: CÓDIGO PHP -->
                <div class="tab-pane fade" id="code-pane" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small font-mono">PHP 8.2+ · Prepared Statements & Facade DB</span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('codeBlock').innerText); this.innerHTML='<i class=\'bi bi-check2\'></i> Copiado!'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Copiar', 2000);">
                            <i class="bi bi-copy me-1"></i> Copiar
                        </button>
                    </div>
                    <pre><code id="codeBlock" class="language-php"><?= htmlspecialchars($dadosCenario['codigo']) ?></code></pre>
                </div>

                <!-- TAB 3: JSON BRUTO -->
                <div class="tab-pane fade" id="json-pane" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small font-mono">Formato JSON do resultado ($result->rows)</span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('jsonBlock').innerText); this.innerHTML='<i class=\'bi bi-check2\'></i> Copiado!'; setTimeout(() => this.innerHTML='<i class=\'bi bi-copy\'></i> Copiar', 2000);">
                            <i class="bi bi-copy me-1"></i> Copiar JSON
                        </button>
                    </div>
                    <pre><code id="jsonBlock" class="language-json"><?= htmlspecialchars(json_encode($linhas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                </div>

                <!-- TAB 4: SCHEMA DAS TABELAS -->
                <div class="tab-pane fade" id="schema-pane" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-dark rounded-3 border border-secondary border-opacity-25">
                                <h6 class="fw-bold text-cyan mb-2 font-mono">clientes</h6>
                                <ul class="list-unstyled small text-secondary mb-0 font-mono">
                                    <li><code>id_cliente</code> (INTEGER PK)</li>
                                    <li><code>nome_cliente</code> (TEXT)</li>
                                    <li><code>uf</code> (TEXT)</li>
                                    <li><code>cidade</code> (TEXT)</li>
                                    <li><code>vip</code> (INTEGER 0|1)</li>
                                    <li><code>saldo_credito</code> (REAL)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 bg-dark rounded-3 border border-secondary border-opacity-25">
                                <h6 class="fw-bold text-cyan mb-2 font-mono">produtos</h6>
                                <ul class="list-unstyled small text-secondary mb-0 font-mono">
                                    <li><code>id_produto</code> (INTEGER PK)</li>
                                    <li><code>id_categoria</code> (INTEGER FK)</li>
                                    <li><code>nome_produto</code> (TEXT)</li>
                                    <li><code>preco</code> (REAL)</li>
                                    <li><code>estoque</code> (INTEGER)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 bg-dark rounded-3 border border-secondary border-opacity-25">
                                <h6 class="fw-bold text-cyan mb-2 font-mono">pedidos & itens</h6>
                                <ul class="list-unstyled small text-secondary mb-0 font-mono">
                                    <li><code>pedidos.id_pedido</code> (INTEGER PK)</li>
                                    <li><code>pedidos.id_cliente</code> (INTEGER FK)</li>
                                    <li><code>pedidos.status</code> (TEXT)</li>
                                    <li><code>itens_pedido.id_item</code> (INTEGER PK)</li>
                                    <li><code>itens_pedido.qtd</code> (INTEGER)</li>
                                    <li><code>itens_pedido.preco_unitario</code> (REAL)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="text-center text-secondary small mt-5 pt-4 border-top border-secondary border-opacity-25">
            <p class="mb-1">
                <strong>DbPHP</strong> é uma biblioteca open-source mantida por <a href="https://github.com/heliomarpm" target="_blank" class="text-decoration-none text-cyan">Heliomar P. Marques</a> sob licença MIT.
            </p>
            <p class="mb-0">
                Par perfeito da biblioteca <a href="https://github.com/heliomarpm/linq-php" target="_blank" class="text-decoration-none text-orange">heliomarpm/linq-php</a>.
            </p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup-templating.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    <script>
    if (typeof Vue !== 'undefined' && document.getElementById('vue-pagination-app')) {
        const { createApp, ref, onMounted } = Vue;

        createApp({
            setup() {
                const items = ref([]);
                const pagination = ref({
                    total: 0,
                    per_page: 4,
                    current_page: 1,
                    last_page: 1,
                    from: 0,
                    to: 0,
                    has_more: false,
                    has_previous: false
                });
                const search = ref('');
                const minPreco = ref(0);
                const perPage = ref(4);
                const loading = ref(false);
                const responseTimeMs = ref('0.00');
                let debounceTimer = null;

                const fetchPage = async (page = 1) => {
                    loading.value = true;
                    const t0 = performance.now();
                    try {
                        const params = new URLSearchParams({
                            api: 'paginate',
                            page: page,
                            per_page: perPage.value,
                            min_preco: minPreco.value,
                            q: search.value
                        });

                        const res = await fetch('?' + params.toString());
                        const json = await res.json();

                        items.value = json.data || [];
                        pagination.value = json.pagination || {};
                        responseTimeMs.value = (performance.now() - t0).toFixed(2);

                        // Atualiza a aba JSON Bruto com o resultado dinâmico
                        const jsonBlock = document.getElementById('jsonBlock');
                        if (jsonBlock) {
                            jsonBlock.innerText = JSON.stringify(json, null, 4);
                            if (window.Prism) {
                                Prism.highlightElement(jsonBlock);
                            }
                        }
                    } catch (err) {
                        console.error('Erro na requisição paginada Vue:', err);
                    } finally {
                        loading.value = false;
                    }
                };

                const debouncedFetch = () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        fetchPage(1);
                    }, 250);
                };

                onMounted(() => {
                    fetchPage(1);
                });

                return {
                    items,
                    pagination,
                    search,
                    minPreco,
                    perPage,
                    loading,
                    responseTimeMs,
                    fetchPage,
                    debouncedFetch
                };
            }
        }).mount('#vue-pagination-app');
    }
    </script>
</body>
</html>