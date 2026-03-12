<?php
// Výchozí referenční konfigurace balíčku.
// Uživatel by měl spustit: php vendor/bin/platformbridge install
// a upravit: config/platform-bridge/bridge-config.php

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    'secretKey'   => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',
    'ttl'         => 3600,
    'api_key'     => 'YOUR_API_KEY_HERE',
    'timeout'     => 30,
    'max_retries' => 3,
    'base_url'    => (function () {
        $isHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $protocol = $isHttps ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}";
    })(),
];
