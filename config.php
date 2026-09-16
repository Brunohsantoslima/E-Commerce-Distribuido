<?php
// config.php - Configurações de Conexão com a Máquina 2 (PostgreSQL)

define('DB_HOST', '127.0.0.1'); // IP do Tailscale / VPN da Máquina 2
define('DB_PORT', '5432');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'postgres');
define('DB_PASS', '190318');
define('DB_TIMEOUT', 3); // Timeout curto em segundos para rápida detecção de falha

// Pasta para arquivos temporários
define('CACHE_FILE', __DIR__ . '/cache/produtos.json');
define('QUEUE_FILE', __DIR__ . '/queue/pedidos_pending.json');

/**
 * Tenta estabelecer conexão PDO com o PostgreSQL (Máquina 2)
 * Retorna o objeto PDO ou null em caso de falha.
 */
function getDbConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => DB_TIMEOUT
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Falha na conexão com a Máquina 2: " . $e->getMessage());
        return null;
    }
}