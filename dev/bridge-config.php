<?php
/**
 * PlatformBridge – Konfigurace API připojení (VÝVOJOVÁ ŠABLONA)
 *
 * Zkopírujte tento soubor jako bridge-config.php a upravte hodnoty:
 *   cp dev/bridge-config.php.dist dev/bridge-config.php
 *
 * Soubor bridge-config.php je v .gitignore (obsahuje API klíč).
 *
 * @see resources/stubs/bridge-config.php  Produkční šablona (stub)
 */

if (!defined('BRIDGE_BOOTSTRAPPED')) {
    http_response_code(403);
    die('Access denied.');
}

return [
    // ─── AI Provider ────────────────────────────────────────────
    // API klíč pro autentizaci vůči AI provideru
    'api_key'     => 'YOUR-DEV-API-KEY',

    // Timeout HTTP požadavku na AI v sekundách
    'timeout'     => 30,

    // Počet opakování při selhání požadavku
    'max_retries' => 3,

    // URL AI API endpointu pro lokální vývoj
    // Upravte podle vašeho lokálního serveru (XAMPP, Laravel Valet, php -S …)
    'base_url'    => 'http://localhost/ai/TEST/',

    // ─── Endpointy ──────────────────────────────────────────────
    // Registrace vlastních AI endpointů.
    // Klíč = název endpointu, hodnota = konfigurační pole.
    'endpoints' => [
		'CreateSubject' => \CreateSubjectEndpoint::class,

        // 'CreateSubject' => [
        //     'generator_id'  => 'subject',
        //     'response_type' => 'nested',
        //     'template'      => '/Components/NestedResult',
        // ],
    ],
];
