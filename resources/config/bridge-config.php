<?php
/**
 * BRIDGE CONFIG
 *
 * Tento soubor nesmí být přístupný z veřejného webu.
 * Proto se na začátku nachází bezpečnostní podmínka,
 * která zabrání přímému spuštění.
 */

// Pokud není definovaná konstanta BOOTSTRAPED, stop.
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

	'base_url' => $_SERVER['HTTP_HOST'] . '/ai/src/PlatformBridge/AI/TEST',
];