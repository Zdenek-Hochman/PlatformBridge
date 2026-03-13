<?php
/**
 * PlatformBridge – Bezpečnostní konfigurace (interní)
 *
 * Tento soubor byl vygenerován příkazem:
 *   php vendor/bin/platformbridge install
 *
 * ⚠️  Tento soubor NESMÍ být přístupný z veřejného webu!
 *     Umísťuje se do {projectRoot}/config/security-config.php
 *     (MIMO public/ složku).
 *
 * Obsahuje tajné klíče pro HMAC podepisování parametrů.
 * Tento soubor se NEPŘEPISUJE při composer update.
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // ─── HMAC Podpis ────────────────────────────────────────────
    // Tajný klíč pro podepisování parametrů (min. 32 znaků).
    // Vygenerujte unikátní klíč pro každé prostředí!
    'secretKey' => 'CHANGE-ME-put-your-long-super-secret-key-here-32chars-minimum',

    // Platnost podepsaných parametrů v sekundách (ochrana proti replay útoku).
    // null = bez expirace
    'ttl' => 3600,
];
