<?php
// sync.php - Página de Sincronização Manual da Fila Local com a Máquina 2
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/queue.php';

$pdo = getDbConnection();
$msg = '';
$type = 'info';

if (!$pdo) {
    $msg = "A Máquina 2 (Banco de Dados) continua indisponível. Não foi possível realizar a sincronização.";
    $type = 'danger';
} else {
    $synced = processQueue($pdo);
    if ($synced > 0) {
        $msg = "Sucesso! {$synced} pedido(s) da fila local foram transferidos e gravados com sucesso na Máquina 2.";
        $type = 'success';
    } else {
        $msg = "Não havia pedidos pendentes ou nenhum pôde ser processado.";
        $type = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sincronização - E-Commerce Distribuído</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container text-center">
    <div class="card shadow border-0 mx-auto" style="max-width: 500px;">
        <div class="card-body p-4">
            <h3 class="card-title mb-4">Central de Sincronização</h3>
            <div class="alert alert-<?= $type ?>" role="alert">
                <?= htmlspecialchars($msg) ?>
            </div>
            <a href="index.php" class="btn btn-secondary mt-3">Voltar para a Loja</a>
        </div>
    </div>
</div>
</body>
</html>
