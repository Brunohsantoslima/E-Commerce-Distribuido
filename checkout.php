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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Status do Pedido - E-Commerce Distribuído</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container text-center">
    <div class="card shadow border-0 mx-auto" style="max-width: 600px;">
        <div class="card-body p-5">
            <?php if ($salvoNoBanco): ?>
                <div class="display-1 text-success mb-3">✅</div>
                <h2 class="card-title text-success mb-3">Pedido Processado com Sucesso!</h2>
                <p class="card-text text-muted">Sua compra foi confirmada e gravada diretamente no Banco de Dados Relacional da <strong>Máquina 2</strong>.</p>
                <div class="badge bg-success mb-4 p-2">Status: PAGO (Persistido no SGBD)</div>
            <?php else: ?>
                <div class="display-1 text-warning mb-3">⏳</div>
                <h2 class="card-title text-warning mb-3">Pedido Recebido (Modo Resiliente)</h2>
                <p class="card-text text-muted">A conexão com o servidor de Banco de Dados (Máquina 2) está indisponível. Seu pedido foi armazenado com segurança no <strong>Buffer de Memória/Fila da Máquina 1</strong> e será sincronizado automaticamente assim que a rede/banco for restabelecido.</p>
                <div class="badge bg-warning text-dark mb-4 p-2">Status: PENDENTE_SYNC (Armazenado na M1)</div>
            <?php endif; ?>

            <div class="border-top pt-3 mt-3 text-start">
                <p><strong>Cliente:</strong> <?= htmlspecialchars($nome) ?></p>
                <p><strong>Total:</strong> R$ <?= number_format($valorTotal, 2, ',', '.') ?></p>
            </div>

            <a href="index.php" class="btn btn-primary mt-4 w-100">Voltar para a Loja</a>
        </div>
    </div>
</div>
</body>
</html>
