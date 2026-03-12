<?php
/**
 * PlatformBridge API Endpoint
 *
 * Publikováno příkazem: php vendor/bin/platformbridge install
 * Umístění: {projectRoot}/public/platformbridge/api.php
 *
 * Můžete přidat vlastní middleware, autentizaci apod.
 * Tento soubor se NEPŘEPISUJE při composer update.
 */

// ─── Autoloader ─────────────────────────────────────────────────
// Relativní cesta: public/platformbridge/api.php → vendor/autoload.php
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';

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
// ApiHandler automaticky detekuje cesty přes PathResolver.
// Konfigurace se načte z config/platform-bridge/bridge-config.php
// s fallbackem na vendor defaults.

\Zoom\PlatformBridge\AI\API\ApiHandler::bootstrap()->handle();
