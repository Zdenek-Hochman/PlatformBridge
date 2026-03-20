<?php

/**
 * PlatformBridge – Konfigurace API připojení
 *
 * Tento soubor byl vygenerován příkazem:
 *   php vendor/bin/platformbridge install
 *
 * Nastavuje adresu a parametry API, na které se odkazuje AJAX z frontendu.
 * Při install se kopíruje do {projectRoot}/public/bridge-config.php.
 * Tento soubor se NEPŘEPISUJE při composer update.
 *
 * ⚠️  Bezpečnostní klíče (secretKey, ttl) jsou v samostatném souboru
 *     security-config.php, ke kterému má přístup pouze interní jádro.
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
    // ─── AI Provider ────────────────────────────────────────────
    // API klíč pro autentizaci vůči AI provideru
    'api_key'     => '5bb61ad879da490705dd959e4cbc3fa6675990e2016b65bc50b695a4f7c4f63a',

    // Timeout HTTP požadavku na AI v sekundách
    'timeout'     => 30,

    // Počet opakování při selhání požadavku
    'max_retries' => 3,

    // URL AI API endpointu, kam se odesílají AJAX požadavky
    'base_url'    => $baseHost . '/ai/TEST/',
    // 'base_url'    => 'https://api.virtualzoom.com/v2/AI/',
	// 'base_url'    => $baseHost . '/Test/vendor/zoom/platform-bridge/src/PlatformBridge/AI/TEST/',
];
