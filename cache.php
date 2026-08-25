<?php
// cache.php - Gerenciador de Cache em Memória/Arquivo Local (Máquina 1)
require_once __DIR__ . '/config.php';

/**
 * Salva a lista de produtos no cache local (JSON)
 */
function saveProductsToCache(array $produtos): bool {
    $data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $produtos
    ];
    return file_put_contents(CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Recupera os produtos do cache local
 */
function getProductsFromCache(): array {
    if (!file_exists(CACHE_FILE)) {
        return ['timestamp' => null, 'data' => []];
    }
    $content = file_get_contents(CACHE_FILE);
    $decoded = json_decode($content, true);
    return $decoded ?: ['timestamp' => null, 'data' => []];
}
