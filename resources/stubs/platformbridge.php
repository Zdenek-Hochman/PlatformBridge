<?php

/**
 * PlatformBridge – Konfigurace instalačních cest
 *
 * Umístěte tento soubor do kořenového adresáře hostitelské aplikace.
 * Installer a PathResolver z něj při každém načtení přečtou cesty
 * pro publikování souborů i runtime resolverování.
 *
 * Pokud tento soubor NEEXISTUJE, použijí se výchozí hodnoty (níže).
 * Stačí uvést jen klíče, které chcete změnit – ostatní se doplní defaults.
 *
 * Všechny cesty jsou RELATIVNÍ vůči kořeni hostitelské aplikace (project root).
 *
 * Vygenerováno příkazem:
 *   php vendor/bin/platformbridge install
 */

return [
    // ─── Assety (JS/CSS) ────────────────────────────────────────
    // Složka, kam se publikují zkompilované JS a CSS soubory.
    'assets_path' => 'public/platformbridge',

    // ─── Bridge konfigurace ─────────────────────────────────────
    // Cesta k bridge-config.php (API klíče, base_url apod.)
    'bridge_config' => 'public/bridge-config.php',

    // ─── Bezpečnostní konfigurace ───────────────────────────────
    // Cesta k security-config.php (HMAC klíče, TTL).
    // ⚠️  Tento soubor by NEMĚL být ve veřejně přístupné složce!
    'security_config' => 'config/security-config.php',

    // ─── JSON konfigurace ───────────────────────────────────────
    // Složka pro blocks.json, layouts.json, generators.json
    'json_path' => 'config/platform-bridge',

    // ─── Cache ──────────────────────────────────────────────────
    // Složka pro cache kompilovaných šablon
    'cache_path' => 'var/cache',

    // ─── API endpoint ───────────────────────────────────────────
    // Cesta k publikovanému API souboru
    'api_file' => 'public/platformbridge/api.php',
];
