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
$pendingOrders = getPendingOrders();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechStore | E-commerce Distribuído</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="nav-shell">
        <a class="brand" href="index.php" aria-label="TechStore início">
            <span class="brand-name">TechStore</span>
            <span class="brand-subtitle">E-commerce Distribuído</span>
        </a>
        <nav class="main-nav" aria-label="Navegação principal">
            <a href="#produtos" aria-current="page">Produtos</a>
            <a href="docs/ARQUITETURA.md">Sobre o sistema</a>
            <a href="#carrinho-section">Carrinho</a>
        </nav>
        <div class="header-actions">
            <?php if ($source === 'database'): ?>
                <span class="connection-pill online"><span class="status-dot"></span>Banco online</span>
            <?php else: ?>
                <span class="connection-pill degraded"><span class="status-dot"></span>Modo degradado</span>
            <?php endif; ?>
            <a class="cart-link" href="#carrinho-section" aria-label="Abrir carrinho">
                <svg class="cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                <span id="cart-count" class="cart-count">0</span>
            </a>
        </div>
    </div>
</header>

<main class="page-shell">
    <?php if ($source === 'cache'): ?>
        <div class="system-alert" role="status">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.3 3.7 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg>
            <div><strong>Banco de dados remoto indisponível</strong><small>Exibindo o catálogo do cache local. Pedidos serão enfileirados para sincronização posterior.</small></div>
            <?php if (!empty($pendingOrders)): ?><a class="sync-link" href="sync.php">Sincronizar fila</a><?php endif; ?>
        </div>
    <?php elseif ($syncMsg): ?>
        <div class="system-alert" role="status"><div><strong>Sincronização concluída</strong><small><?= htmlspecialchars($syncMsg) ?></small></div></div>
    <?php endif; ?>

    <section class="intro" id="produtos">
        <div>
            <div class="eyebrow">Tecnologia confiável, mesmo offline</div>
            <h1>Hardware de alta performance</h1>
            <p class="intro-copy">Encontre componentes para montar seu próximo setup. A loja continua operando mesmo quando a comunicação com o banco remoto é interrompida.</p>
        </div>
        <div class="catalog-meta"><strong><?= count($produtos) ?></strong> produtos disponíveis<br><span><?= $source === 'database' ? 'Dados sincronizados' : 'Última atualização: ' . htmlspecialchars($cacheTime ?: 'N/A') ?></span></div>
    </section>

    <?php if (!empty($pendingOrders)): ?>
        <div class="queue-pill"><span class="status-dot"></span><?= count($pendingOrders) ?> pedido(s) aguardando sincronização</div>
    <?php endif; ?>

    <section class="catalog-layout">
        <aside class="filter-panel" aria-label="Categorias de produtos">
            <h2>Categorias</h2>
            <ul class="category-list">
                <li><a class="active" href="#produtos">Todos os produtos <span><?= count($produtos) ?></span></a></li>
                <li><a href="#produtos">Componentes <span>+</span></a></li>
                <li><a href="#produtos">Notebooks <span>+</span></a></li>
                <li><a href="#produtos">Periféricos <span>+</span></a></li>
            </ul>
            <div class="metric-box"><strong>Operação distribuída</strong><p>Catálogo e pedidos protegidos por cache local e fila de contingência.</p></div>
        </aside>

        <div>
            <div class="products-heading"><h2>Todos os produtos</h2><input class="search-box" id="product-search" type="search" placeholder="Buscar produto..." aria-label="Buscar produto"></div>
            <div class="product-grid">
                <?php if (empty($produtos)): ?>
                    <div class="surface-panel"><p class="empty-state">Nenhum produto cadastrado ou cache vazio.</p></div>
                <?php else: ?>
                    <?php foreach ($produtos as $p): ?>
                        <?php $stock = (int) ($p['estoque'] ?? 0); $artClass = $stock > 10 ? 'storage' : ($p['id'] % 2 === 0 ? 'memory' : ''); ?>
                        <article class="product-card">
                            <div class="product-image">
                                <?php if (!empty($p['imagem_url'])): ?><img src="<?= htmlspecialchars($p['imagem_url']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>"><?php else: ?><span class="product-art <?= $artClass ?>" aria-hidden="true"></span><?php endif; ?>
                            </div>
                            <div class="product-content">
                                <span class="product-category">Tecnologia</span>
                                <h3 class="product-name"><?= htmlspecialchars($p['nome']) ?></h3>
                                <p class="product-description"><?= htmlspecialchars($p['descricao'] ?? 'Produto selecionado para seu setup.') ?></p>
                                <div class="stock <?= $stock <= 0 ? 'out' : '' ?>"><span class="stock-dot"></span><?= $stock > 0 ? 'Em estoque: ' . $stock . ' unidades' : 'Produto indisponível' ?></div>
                                <div class="product-footer"><span class="price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span><button class="primary-button" type="button" onclick="addToCart(<?= (int) $p['id'] ?>, <?= htmlspecialchars(json_encode($p['nome'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= (float) $p['preco'] ?>)" <?= $stock <= 0 ? 'disabled' : '' ?>>Adicionar</button></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="cart-section" id="carrinho-section">
        <div class="surface-panel"><h2>Seu carrinho</h2><div class="cart-items" id="cart-items"></div></div>
        <div class="surface-panel"><h2>Resumo do pedido</h2><div class="summary-row"><span>Subtotal</span><strong id="cart-total">R$ 0,00</strong></div><div class="summary-row"><span>Frete</span><strong class="free-shipping">Grátis</strong></div><div class="summary-row summary-total"><span>Total</span><strong id="cart-total-summary">R$ 0,00</strong></div><form action="checkout.php" method="POST" id="checkout-form" class="checkout-form"><input type="hidden" name="cart_data" id="cart-data"><label>Nome completo<input type="text" name="cliente_nome" placeholder="Seu nome" required></label><label>E-mail<input type="email" name="cliente_email" placeholder="seu@email.com" required></label><p class="form-note">O pedido será gravado no banco remoto ou protegido na fila local, conforme a disponibilidade da conexão.</p><button class="primary-button" type="submit" id="checkout-button" disabled>Finalizar pedido</button></form></div>
    </section>
</main>
<div id="toast" class="toast" role="status"></div>
<footer class="site-footer">© 2026 TechStore Distribuída · Projeto acadêmico de Sistemas Distribuídos</footer>
<script src="assets/js/app.js"></script>
</body>
</html>
