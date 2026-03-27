<?php
/**
 * PlatformBridge – Bezpečnostní konfigurace (VÝVOJOVÁ ŠABLONA)
 *
 * Zkopírujte tento soubor jako security-config.php:
 *   cp dev/security-config.php.dist dev/security-config.php
 *
 * Soubor security-config.php je v .gitignore (obsahuje tajný klíč).
 *
 * @see resources/stubs/security-config.php  Produkční šablona (stub)
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // ─── HMAC Podpis ────────────────────────────────────────────
    // Tajný klíč pro podepisování parametrů (min. 32 znaků).
    // Pro dev stačí libovolný řetězec – jen musí být shodný mezi frontendem a API.
    'secretKey' => 'dev-only-secret-key-minimum-32-characters-long!!',

    // Platnost podepsaných parametrů v sekundách.
    // null = bez expirace (pohodlnější pro vývoj)
    'ttl' => null,
];
