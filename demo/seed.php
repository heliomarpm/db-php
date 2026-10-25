<?php

declare(strict_types=1);

use HeliomarPM\DbPHP\DB;
use HeliomarPM\DbPHP\Contracts\DriverInterface;

/**
 * Cria e popula uma base SQLite em memória com dados ricos para o laboratório.
 */
function createDemoDatabase(): DriverInterface
{
    $db = DB::sqlite();

    // 1. Categorias
    $db->execute("
        CREATE TABLE IF NOT EXISTS categorias (
            id_categoria INTEGER PRIMARY KEY,
            nome_categoria TEXT NOT NULL
        )
    ");
    $db->execute("
        INSERT INTO categorias (id_categoria, nome_categoria) VALUES
        (1, 'Insumo'),
        (2, 'Peça'),
        (3, 'Acessório'),
        (4, 'Equipamento')
    ");

    // 2. Clientes
    $db->execute("
        CREATE TABLE IF NOT EXISTS clientes (
            id_cliente INTEGER PRIMARY KEY,
            nome_cliente TEXT NOT NULL,
            uf TEXT NOT NULL,
            cidade TEXT NOT NULL,
            vip INTEGER NOT NULL DEFAULT 0,
            saldo_credito REAL NOT NULL DEFAULT 0.00
        )
    ");
    $db->execute("
        INSERT INTO clientes (id_cliente, nome_cliente, uf, cidade, vip, saldo_credito) VALUES
        (1, 'Ana Sousa', 'MG', 'Belo Horizonte', 1, 150.00),
        (2, 'Carlos Lima', 'SP', 'Campinas', 0, 0.00),
        (3, 'Helio Marques', 'MG', 'Uberlândia', 1, 320.50),
        (4, 'Cliente Ocioso', 'RJ', 'Niterói', 0, 0.00),
        (5, 'Beatriz Rocha', 'BA', 'Salvador', 1, 75.00),
        (6, 'Rodrigo Ramos', 'PR', 'Curitiba', 0, 50.00),
        (7, 'Fernanda Costa', 'RS', 'Porto Alegre', 1, 0.00),
        (8, 'Lucas Mendes', 'SP', 'São Paulo', 0, 110.00)
    ");

    // 3. Produtos
    $db->execute("
        CREATE TABLE IF NOT EXISTS produtos (
            id_produto INTEGER PRIMARY KEY,
            id_categoria INTEGER NOT NULL,
            nome_produto TEXT NOT NULL,
            preco REAL NOT NULL,
            estoque INTEGER NOT NULL,
            FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
        )
    ");
    $db->execute("
        INSERT INTO produtos (id_produto, id_categoria, nome_produto, preco, estoque) VALUES
        (101, 1, 'PLA Preto 1kg', 89.90, 45),
        (102, 1, 'PETG Branco 1kg', 95.50, 30),
        (103, 2, 'Bico Extrusor 0.4mm', 35.00, 100),
        (104, 2, 'Mesa PEI Texturizada', 120.00, 15),
        (105, 1, 'Resina 8K (Lançamento)', 180.00, 25),
        (106, 3, 'Secador de Filamento', 299.90, 12),
        (107, 2, 'Sensor Nivelamento Auto', 149.00, 20),
        (108, 1, 'Filamento TPU Flexível 1kg', 139.90, 8),
        (109, 3, 'Alicate de Precisão', 28.50, 50),
        (110, 4, 'Impressora 3D CoreXY K1', 3450.00, 3)
    ");

    // 4. Pedidos
    $db->execute("
        CREATE TABLE IF NOT EXISTS pedidos (
            id_pedido INTEGER PRIMARY KEY,
            id_cliente INTEGER NOT NULL,
            canal TEXT NOT NULL,
            status TEXT NOT NULL,
            data_pedido TEXT NOT NULL,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        )
    ");
    $db->execute("
        INSERT INTO pedidos (id_pedido, id_cliente, canal, status, data_pedido) VALUES
        (1, 1, 'online', 'concluido', '2026-10-20 14:30:00'),
        (2, 3, 'loja', 'concluido', '2026-10-21 10:15:00'),
        (3, 2, 'online', 'pendente', '2026-10-21 16:40:00'),
        (4, 1, 'online', 'concluido', '2026-10-22 09:10:00'),
        (5, 5, 'online', 'concluido', '2026-10-22 11:25:00'),
        (6, 6, 'loja', 'concluido', '2026-10-23 15:50:00'),
        (7, 7, 'online', 'concluido', '2026-10-23 18:05:00'),
        (8, 2, 'online', 'cancelado', '2026-10-24 08:30:00'),
        (9, 5, 'loja', 'concluido', '2026-10-24 12:00:00'),
        (10, 8, 'online', 'concluido', '2026-10-24 14:15:00')
    ");

    // 5. Itens Pedido
    $db->execute("
        CREATE TABLE IF NOT EXISTS itens_pedido (
            id_item INTEGER PRIMARY KEY,
            id_pedido INTEGER NOT NULL,
            id_produto INTEGER NOT NULL,
            qtd INTEGER NOT NULL,
            preco_unitario REAL NOT NULL,
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
            FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)
        )
    ");
    $db->execute("
        INSERT INTO itens_pedido (id_item, id_pedido, id_produto, qtd, preco_unitario) VALUES
        (1, 1, 101, 2, 89.90),
        (2, 1, 103, 1, 35.00),
        (3, 2, 103, 5, 35.00),
        (4, 3, 101, 1, 89.90),
        (5, 4, 102, 3, 95.50),
        (6, 5, 106, 1, 299.90),
        (7, 6, 107, 2, 149.00),
        (8, 7, 108, 4, 139.90),
        (9, 8, 109, 3, 28.50),
        (10, 9, 110, 1, 3450.00),
        (11, 10, 101, 5, 89.90)
    ");

    // 6. Contas (Para simulação de transações atômicas)
    $db->execute("
        CREATE TABLE IF NOT EXISTS contas (
            id_conta INTEGER PRIMARY KEY,
            titular TEXT NOT NULL,
            saldo REAL NOT NULL
        )
    ");
    $db->execute("
        INSERT INTO contas (id_conta, titular, saldo) VALUES
        (1, 'Empresa Matriz Ltda', 10000.00),
        (2, 'Filial Sul Peças', 2500.00),
        (3, 'Fundo de Reserva', 5000.00)
    ");

    return $db;
}
