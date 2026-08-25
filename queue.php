<?php
// queue.php - Gerenciador de Fila Local de Pedidos (Buffer de Escrita para Tolerância a Falhas)
require_once __DIR__ . '/config.php';

/**
 * Adiciona um pedido à fila pendente local (Máquina 1)
 */
function enqueueOrder(array $orderData): bool {
    $orders = getPendingOrders();
    $orderData['queued_at'] = date('Y-m-d H:i:s');
    $orderData['local_id'] = uniqid('PED_');
    $orders[] = $orderData;
    
    return file_put_contents(QUEUE_FILE, json_encode($orders, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Obtém todos os pedidos pendentes na fila local
 */
function getPendingOrders(): array {
    if (!file_exists(QUEUE_FILE)) {
        return [];
    }
    $content = file_get_contents(QUEUE_FILE);
    return json_decode($content, true) ?: [];
}

/**
 * Tenta processar a fila local gravando os pedidos no Banco da Máquina 2
 */
function processQueue($pdo): int {
    $pending = getPendingOrders();
    if (empty($pending)) {
        return 0;
    }

    $processedCount = 0;
    $remainingOrders = [];

    foreach ($pending as $order) {
        try {
            $pdo->beginTransaction();

            // Inserir pedido
            $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_nome, cliente_email, valor_total, status) VALUES (?, ?, ?, 'SINCRONIZADO') RETURNING id");
            $stmt->execute([$order['cliente_nome'], $order['cliente_email'], $order['valor_total']]);
            $pedidoId = $stmt->fetchColumn();

            // Inserir itens
            $stmtItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
            foreach ($order['itens'] as $item) {
                $stmtItem->execute([$pedidoId, $item['produto_id'], $item['quantidade'], $item['preco_unitario']]);
            }

            $pdo->commit();
            $processedCount++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao sincronizar pedido local " . $order['local_id'] . ": " . $e->getMessage());
            // Mantém na fila se falhar
            $remainingOrders[] = $order;
        }
    }

    // Salva apenas os pedidos que ainda não foram sincronizados
    file_put_contents(QUEUE_FILE, json_encode($remainingOrders, JSON_PRETTY_PRINT));
    return $processedCount;
}
