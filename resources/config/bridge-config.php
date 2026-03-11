<?php
/**
 * BRIDGE CONFIG
 *
 * Konfigurační soubor pro PlatformBridge.
 * Tento soubor NESMÍ být přístupný z veřejného webu.
 *
 * Nachází se vždy v resources/config/bridge-config.php uvnitř balíčku.
 * Nikdy se nekopíruje do public/ složky.
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

// ─── Auto-detekce hostitele ────────────────────────────────────
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

    // ─── Base URL pro AI API požadavky ──────────────────────────
    // Automaticky se skládá z protokolu + hostitele.
    // Localhost: http://localhost/ai/src/PlatformBridge/AI/TEST
    // Server:    https://domena.cz/cesta/k/api
    //
    // Cestu za $baseHost upravte podle svého prostředí.
    'base_url' => $baseHost . '/ai/src/PlatformBridge/AI/TEST',
];