<?php

/**
 * PlatformBridge Configuration
 *
 * Tento soubor obsahuje citlivé konfigurační údaje.
 * Ujistěte se, že NENÍ veřejně přístupný z webu.
 *
 * Při instalaci se publikuje do: {projectRoot}/config/bridge-config.php
 *
 * @see \Zoom\PlatformBridge\PlatformBridgeConfig
 */

// Bezpečnostní kontrola - zabraňuje přímému přístupu z prohlížeče
if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // ─── HMAC Podpis ────────────────────────────────────────────
    // Tajný klíč pro podepisování parametrů (min. 32 znaků)
    'secretKey' => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',

    // Expirace podepsaných parametrů v sekundách (3600 = 1 hodina)
    'ttl' => 3600,

    // ─── AI Provider ────────────────────────────────────────────
    // API klíč pro AI providera
    'api_key' => 'YOUR_API_KEY_HERE',

    // Timeout pro API požadavky v sekundách
    'timeout' => 30,

    // Maximální počet opakování při selhání
    'max_retries' => 3,
];
