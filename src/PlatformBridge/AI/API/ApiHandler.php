<?php

/**
 * API Handler - Jádro systému
 *
 * Zpracovává příchozí requesty, ověřuje podpisy a deleguje
 * na registrované endpointy. Jádro se nestará o strukturu dat,
 * tu definují jednotlivé EndpointDefinition třídy.
 */

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

use Zoom\PlatformBridge\AI\AiClient;
use Zoom\PlatformBridge\AI\AiClientConfig;
use Zoom\PlatformBridge\AI\AiResponseRenderer;
use Zoom\PlatformBridge\AI\AiException;
use Zoom\PlatformBridge\AI\API\EndpointRegistry;
use Zoom\PlatformBridge\Config\ConfigManager;
use Zoom\PlatformBridge\Security\SignedParams;
use Zoom\PlatformBridge\Security\SecurityException;

// header('Content-Type: application/json');

try {
    /** ----------------------------------------
     *  1) Načtení JSON vstupu
     * -------------------------------------- */
    $input = json_decode(file_get_contents("php://input"), true, 512, JSON_THROW_ON_ERROR);

    /** ----------------------------------------
     * 2) Konfigurace
     * -------------------------------------- */
    define('BRIDGE_BOOTSTRAPPED', true);
    $configPath = dirname(__DIR__, 4) . '/resources/config/bridge-config.php';

    if (!file_exists($configPath)) {
        throw new \RuntimeException('Configuration file not found.');
    }

    $config = require $configPath;
    $secretKey = $config['secretKey'] ?? null;
    $ttl = $config['ttl'] ?? null;

    if (!$secretKey) {
        throw new \RuntimeException('Secret key not configured.');
    }

    /** ----------------------------------------
     * 3a) Načtení ConfigManager pro JSON konfigurace
     * -------------------------------------- */
    $configDir = dirname(__DIR__, 4) . '/resources/config/defaults';
    $configManager = ConfigManager::create($configDir);

    /** ----------------------------------------
     * 3b) Ověření HMAC podpisu
     * -------------------------------------- */
    if (!isset($input['__ai_signed'])) {
        throw new SecurityException('Missing signed params (__ai_signed).');
    }

    $signedParams = new SignedParams($secretKey, $ttl);
    $verifiedParams = $signedParams->verify($input['__ai_signed']);

    unset($input['__ai_signed']);

    /** ----------------------------------------
     * 4) Získání endpointu z registru
     * -------------------------------------- */
    $endpointName = $verifiedParams['config']['endpoint'] ?? null;

    if (!$endpointName) {
        throw AiException::invalidRequest('Chybí název endpointu v konfiguraci.');
    }

    $registry = EndpointRegistry::getInstance();
    $registry->setConfigManager($configManager);
    $endpointDef = $registry->getOrFail($endpointName);

    /** ----------------------------------------
     * 4b) Detekce single-key módu (__generate_key)
     *
     * Pokud frontend posílá __generate_key, přepneme endpoint
     * do single-key režimu. Data přichází z relace (session),
     * nikoliv z aktuálního formuláře.
     *
     * AI API pak generuje pouze zadaný klíč → šetří tokeny.
     * -------------------------------------- */
    $generateKey = $input['__generate_key'] ?? null;
    unset($input['__generate_key']); // neodesílat jako formulářový vstup

    if ($generateKey !== null && is_string($generateKey) && $generateKey !== '') {
        $endpointDef->setSingleKeyMode($generateKey);
    }

    /** ----------------------------------------
     * 5) Sestavení requestu přes EndpointDefinition
     * -------------------------------------- */
    $request = $endpointDef->createRequest(
        $input,                              // Prompt/formulářová data
        $verifiedParams['get'] ?? [],        // GET parametry
        $verifiedParams['body'] ?? [],		 // Body parametry
    );

    // Přidáme extra headers pokud jsou
    foreach (($verifiedParams['headers'] ?? []) as $name => $value) {
        $request->withHeader($name, $value);
    }

    /** ----------------------------------------
     * 6) AiClient + odeslání
     * -------------------------------------- */
    $clientConfig = AiClientConfig::fromArray([
        'api_key' => $config['api_key'] ?? "YOUR_API_KEY_HERE",
        'timeout' => $config['timeout'] ?? 30,
        'max_retries' => $config['max_retries'] ?? 3,
        'debug' => defined('DEBUG_MODE')
    ]);

    $client = new AiClient($clientConfig);
    $response = $client->send($request);

    /** ----------------------------------------
     * 7) Parsování odpovědi podle typu endpointu
     * -------------------------------------- */
    $parsedData = $endpointDef->parseResponse($response->getResponse());

    /** ----------------------------------------
     * 8) Renderování pomocí šablony endpointu
     * -------------------------------------- */
    $renderer = AiResponseRenderer::create();


    $html = $renderer->render($parsedData, $endpointDef->getActiveTemplate(), [
        'variant' => $endpointDef->detectVariant($input),
        'response_type' => $endpointDef->getActiveResponseType(),
        'single_key' => $endpointDef->getSingleKey(),
    ]);

    $response = $response->toArray();

    // Odpověď
    echo json_encode([
    	"api" => [
    		'success' => $response['success'],
    		'status_code' => $response['status_code'],
    		'meta' => $response['meta'],
    	],
    	"provider" => [
    		'success' => true,
    		'status_code' => 200,
    		'meta' => [
    			'endpoint' => $endpointName,
    			'response_type' => $endpointDef->getActiveResponseType(),
    			'single_key' => $endpointDef->getSingleKey(),
    		]
    	],
    	"data" => [
    		"raw" => $response['response'],
    		'parsed' => $parsedData,
    		'html' => $html,
    	]
    ], JSON_UNESCAPED_UNICODE);

} catch (SecurityException $e) {
    http_response_code(403);
    echo json_encode([
        "api" => [
            "success" => false,
            "status_code" => 403,
            "error" => [
                "type" => "security",
                "message" => "Forbidden",
                "code" => 403
            ]
        ],
        "provider" => null,
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

} catch (AiException $e) {
    $statusCode = match ($e->getCode()) {
        AiException::ERROR_VALIDATION => 422,
        AiException::ERROR_INVALID_REQUEST => 400,
        AiException::ERROR_TIMEOUT => 504,
        default => 500
    };

    http_response_code($statusCode);

    echo json_encode([
        "api" => [
            "success" => false,
            "status_code" => $e->getCode(),
            "error" => [
                "type" => "ai_provider",
                "message" => $e->getMessage(),
                "context" => $e->getContext(),
                "code" => $e->getCode()
            ]
        ],
        "provider" => null,
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

} catch (\JsonException $e) {
    http_response_code(400);

    echo json_encode([
        "api" => [
            "success" => false,
            "status_code" => 400,
            "error" => [
                "type" => "invalid_json",
                "message" => "Neplatný JSON vstup",
                "context" => null,
                "code" => 400
            ]
        ],
        "provider" => null,
        "data" => null
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);

    echo json_encode([
        "api" => [
            "success" => false,
            "status_code" => 500,
            "error" => [
                "type" => "internal_error",
                "message" =>  $e->getMessage(),
                "context" =>  $e->getTraceAsString(),
                "code" => 500
            ]
        ],
        "provider" => null,
        "data" => null
    ], JSON_UNESCAPED_UNICODE);
}
