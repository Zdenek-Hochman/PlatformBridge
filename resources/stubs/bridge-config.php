<?php
/**
 * PlatformBridge – Uživatelská konfigurace
 *
 * Tento soubor byl vygenerován příkazem:
 *   php vendor/bin/platformbridge install
 *
 * Upravte hodnoty podle svého prostředí.
 * Tento soubor se NEPŘEPISUJE při composer update.
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

$isHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$protocol = $isHttps ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseHost = "{$protocol}://{$host}";

return [
    // ─── HMAC Podpis ────────────────────────────────────────────
    'secretKey' => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',
    'ttl'       => 3600,

    // ─── AI Provider ────────────────────────────────────────────
    'api_key'     => 'YOUR_API_KEY_HERE',
    'timeout'     => 30,
    'max_retries' => 3,
    'base_url'    => $baseHost . '/api/ai',
];
