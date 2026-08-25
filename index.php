<?php
// index.php - Front-end e Catálogo com Fallback e Degradação Graciosa
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/queue.php';

$pdo = getDbConnection();
$source = '';
$produtos = [];
$cacheTime = '';
$syncMsg = '';

// Tenta sincronizar pedidos da fila se o banco estiver online
if ($pdo) {
    $synced = processQueue($pdo);
    if ($synced > 0) {
        $syncMsg = "Sincronização realizada! {$synced} pedido(s) pendente(s) gravado(s) no Banco da Máquina 2.";
    }
}

// 1. Tenta buscar no Banco de Dados (Máquina 2)
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT p.*, COALESCE(e.quantidade, 0) as estoque FROM produtos p LEFT JOIN estoque e ON p.id = e.produto_id ORDER BY p.id ASC");
        $produtos = $stmt->fetchAll();
        saveProductsToCache($produtos); // Atualiza cache na Máquina 1
        $source = 'database';
    } catch (Exception $e) {
        $pdo = null; // Força fallback se query falhar
    }
}

// 2. Fallback: Se o banco falhou ou está offline, busca do Cache Local (Máquina 1)
if (!$pdo) {
    $cached = getProductsFromCache();
    $produtos = $cached['data'];
    $cacheTime = $cached['timestamp'];
    $source = 'cache';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>E-Commerce Distribuído - PHP/IIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-status { font-size: 0.9rem; padding: 0.5em 0.8em; }
        .card-product { transition: transform 0.2s; }
        .card-product:hover { transform: translateY(-3px); }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <header class="pb-3 mb-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold text-dark">🛒 TechStore Distribuída</h1>
            <span class="text-muted">Sistemas Distribuídos - IIS (M1) & PostgreSQL (M2)</span>
        </div>
        <div>
            <?php if ($source === 'database'): ?>
                <span class="badge bg-success badge-status">🟢 Conectado à Máquina 2 (DB Online)</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark badge-status">⚠️ Modo Degradado (Cache Local M1)</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($syncMsg): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>🔄 Sincronização Automática:</strong> <?= htmlspecialchars($syncMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($source === 'cache'): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Aviso de Tolerância a Falhas:</strong> A comunicação com a Máquina 2 (Banco de Dados) está indisponível no momento. Exibindo dados do cache em memória temporária (Última atualização: <?= htmlspecialchars($cacheTime ?: 'N/A') ?>).
        </div>
    <?php endif; ?>

    <!-- Fila de Pedidos Pendentes -->
    <?php $pendingOrders = getPendingOrders(); ?>
    <?php if (!empty($pendingOrders)): ?>
        <div class="alert alert-secondary d-flex justify-content-between align-items-center">
            <span>📦 Existem <strong><?= count($pendingOrders) ?></strong> pedido(s) armazenado(s) na fila local (M1) aguardando o retorno da Máquina 2.</span>
            <a href="sync.php" class="btn btn-sm btn-outline-dark">Tentar Sincronizar Agora</a>
        </div>
    <?php endif; ?>

    <!-- Lista de Produtos -->
    <h2 class="h4 mb-3">Catálogo de Produtos</h2>
    <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <?php if (empty($produtos)): ?>
            <div class="col-12"><p class="text-muted">Nenhum produto cadastrado ou cache vazio.</p></div>
        <?php else: ?>
            <?php foreach ($produtos as $p): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm card-product">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($p['nome']) ?></h5>
                            <p class="card-text text-muted small"><?= htmlspecialchars($p['descricao'] ?? '') ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="h5 mb-0 text-primary">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                                <small class="text-secondary">Estoque: <?= $p['estoque'] ?? 'N/A' ?></small>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0">
                            <button onclick="adicionarAoCarrinho(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nome']) ?>', <?= $p['preco'] ?>)" class="btn btn-outline-primary w-100">+ Adicionar ao Carrinho</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Carrinho & Checkout -->
    <div class="row" id="carrinho-section">
        <div class="col-md-8 mx-auto">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white fw-bold">🛒 Seu Carrinho (Armazenado no Navegador / Client-Side)</div>
                <div class="card-body">
                    <div id="carrinho-itens" class="mb-3">
                        <p class="text-muted">Seu carrinho está vazio.</p>
                    </div>
                    <form action="checkout.php" method="POST" id="form-checkout">
                        <input type="hidden" name="cart_data" id="cart_data">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <input type="text" name="cliente_nome" class="form-control" placeholder="Seu Nome" required>
                            </div>
                            <div class="col-md-6">
                                <input type="email" name="cliente_email" class="form-control" placeholder="Seu E-mail" required>
                            </div>
                        </div>
                        <button type="submit" id="btn-finalizar" class="btn btn-success w-100 fw-bold" disabled>Finalizar Compra</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gerenciamento do Carrinho via localStorage
let carrinho = JSON.parse(localStorage.getItem('carrinho_sd')) || [];

function renderCarrinho() {
    const container = document.getElementById('carrinho-itens');
    const inputCart = document.getElementById('cart_data');
    const btnFinalizar = document.getElementById('btn-finalizar');

    if (carrinho.length === 0) {
        container.innerHTML = '<p class="text-muted">Seu carrinho está vazio.</p>';
        btnFinalizar.disabled = true;
        inputCart.value = '';
        return;
    }

    let html = '<ul class="list-group mb-3">';
    let total = 0;
    carrinho.forEach((item, index) => {
        let subtotal = item.preco * item.qtd;
        total += subtotal;
        html += `<li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>${item.nome}</strong><br>
                <small class="text-muted">R$ ${item.preco.toFixed(2)} x ${item.qtd}</small>
            </div>
            <div>
                <span class="fw-bold me-3">R$ ${subtotal.toFixed(2)}</span>
                <button class="btn btn-sm btn-danger" onclick="removerDoCarrinho(${index})">X</button>
            </div>
        </li>`;
    });
    html += `</ul><div class="d-flex justify-content-between h5"><span>Total:</span><span class="text-success fw-bold">R$ ${total.toFixed(2)}</span></div>`;
    
    container.innerHTML = html;
    inputCart.value = JSON.stringify(carrinho);
    btnFinalizar.disabled = false;
}

function adicionarAoCarrinho(id, nome, preco) {
    let item = carrinho.find(i => i.id === id);
    if (item) {
        item.qtd++;
    } else {
        carrinho.push({ id, nome, preco, qtd: 1 });
    }
    localStorage.setItem('carrinho_sd', JSON.stringify(carrinho));
    renderCarrinho();
}

function removerDoCarrinho(index) {
    carrinho.splice(index, 1);
    localStorage.setItem('carrinho_sd', JSON.stringify(carrinho));
    renderCarrinho();
}

document.getElementById('form-checkout').addEventListener('submit', function() {
    localStorage.removeItem('carrinho_sd');
});

renderCarrinho();
</script>
</body>
</html>
