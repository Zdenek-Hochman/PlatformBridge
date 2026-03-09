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
    // Tvůj tajný klíč pro podepisování dat
    'secretKey' => 'put-your-long-super-secret-key-here-32chars-minimum',
    // Volitelné: expirace podepsaných parametrů
    'ttl' => 3600,
];