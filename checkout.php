<?php
// checkout.php - Processamento de Checkout com Fallback para Fila Local (Buffer Assíncrono)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/queue.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nome = trim($_POST['cliente_nome'] ?? '');
$email = trim($_POST['cliente_email'] ?? '');
$cartJson = $_POST['cart_data'] ?? '[]';
$cart = json_decode($cartJson, true) ?: [];

if (empty($nome) || empty($email) || empty($cart)) {
    die("Dados inválidos. <a href='index.php'>Voltar</a>");
}

// Prepara dados do pedido
$valorTotal = 0;
$itens = [];
foreach ($cart as $item) {
    $subtotal = $item['preco'] * $item['qtd'];
    $valorTotal += $subtotal;
    $itens[] = [
        'produto_id' => $item['id'],
        'quantidade' => $item['qtd'],
        'preco_unitario' => $item['preco']
    ];
}

$pedidoData = [
    'cliente_nome' => $nome,
    'cliente_email' => $email,
    'valor_total' => $valorTotal,
    'itens' => $itens
];

$pdo = getDbConnection();
$salvoNoBanco = false;

// 1. Tenta gravar diretamente na Máquina 2 (PostgreSQL)
if ($pdo) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_nome, cliente_email, valor_total, status) VALUES (?, ?, ?, 'PAGO') RETURNING id");
        $stmt->execute([$nome, $email, $valorTotal]);
        $pedidoId = $stmt->fetchColumn();

        $stmtItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        foreach ($itens as $it) {
            $stmtItem->execute([$pedidoId, $it['produto_id'], $it['quantidade'], $it['preco_unitario']]);
        }

        $pdo->commit();
        $salvoNoBanco = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $salvoNoBanco = false;
    }
}

// 2. Se a Máquina 2 estiver offline, usa a Fila Local (Store-and-Forward)
if (!$salvoNoBanco) {
    enqueueOrder($pedidoData);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status do pedido | TechStore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="result-page">
    <section class="result-panel">
        <?php if ($salvoNoBanco): ?>
            <div class="result-icon success" aria-hidden="true">✓</div>
            <h1>Pedido confirmado</h1>
            <p>Sua compra foi gravada no banco de dados remoto da Máquina 2.</p>
            <span class="status-badge success">Status: PAGO</span>
        <?php else: ?>
            <div class="result-icon pending" aria-hidden="true">!</div>
            <h1>Pedido recebido</h1>
            <p>O banco remoto está temporariamente indisponível. Seu pedido foi protegido na fila local da Máquina 1 e será sincronizado posteriormente.</p>
            <span class="status-badge pending">Status: PENDENTE_SYNC</span>
        <?php endif; ?>
        <div class="result-details"><div><span>Cliente</span><strong><?= htmlspecialchars($nome) ?></strong></div><div><span>Total</span><strong>R$ <?= number_format($valorTotal, 2, ',', '.') ?></strong></div></div>
        <a href="index.php" class="primary-button" style="display:block">Voltar para a loja</a>
    </section>
</main>
</body>
</html>
