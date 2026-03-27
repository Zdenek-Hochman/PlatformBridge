<?php
/**
 * PlatformBridge API Endpoint – VÝVOJOVÁ VERZE
 *
 * Tento soubor slouží jako API vstupní bod pro lokální vývoj (localhost).
 * Na rozdíl od resources/stubs/api.php neobsahuje žádné placeholdery –
 * autoloader je resolvován přímo relativní cestou.
 *
 * V produkci se tento soubor NEPOUŽÍVÁ. Installer publikuje
 * resources/stubs/api.php s automaticky vypočtenou cestou k autoloaderu.
 *
 * @see resources/stubs/api.php  Produkční šablona (stub)
 */

// ─── Autoloader ─────────────────────────────────────────────────
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'api' => [
            'success' => false,
            'error' => [
                'type' => 'configuration',
                'message' => 'Autoloader not found. Run "composer install" first.',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

require_once $autoloadPath;

// ─── Bootstrap ──────────────────────────────────────────────────
\Zoom\PlatformBridge\AI\API\ApiHandler::bootstrap()->handle();
