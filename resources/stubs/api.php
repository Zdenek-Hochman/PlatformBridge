<?php

/**
 * PlatformBridge API Endpoint
 *
 * Tento soubor je automaticky publikován instalátorem PlatformBridge.
 * Zpracovává AI API požadavky.
 *
 * Umístění: {hostRoot}/public/platformbridge/api.php
 * Autoloader: {hostRoot}/vendor/autoload.php (2 úrovně výš)
 *
 * Můžete jej upravit - přidat middleware, autentizaci apod.
 *
 * @see \Zoom\PlatformBridge\AI\API\ApiHandler
 */

$autoloadPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'api' => [
            'success' => false,
            'status_code' => 500,
            'error' => [
                'type' => 'configuration',
                'message' => 'Autoloader not found. Run "composer install" first.',
                'code' => 500,
            ],
        ],
        'provider' => null,
        'data' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

require_once $autoloadPath;

// Zpracování požadavku
\Zoom\PlatformBridge\AI\API\ApiHandler::bootstrap()->handle();
