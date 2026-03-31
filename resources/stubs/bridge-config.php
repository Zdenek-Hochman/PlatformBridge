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

return [
    // ─── AI Provider ────────────────────────────────────────────
    // API klíč pro autentizaci vůči AI provideru
    'api_key'     => 'CHANGE-ME-your-api-key-here',

    // Timeout HTTP požadavku na AI v sekundách
    'timeout'     => 30,

    // Počet opakování při selhání požadavku
    'max_retries' => 3,

    // URL AI API endpointu, kam se odesílají AJAX požadavky
    // Nastavte na konkrétní URL vašeho prostředí (nedoporučuje se dynamická detekce z $_SERVER)
    'base_url'    => 'https://your-domain.com/platformbridge/api.php',

    'endpoints' => [],
];