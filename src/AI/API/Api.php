<?php

require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/../../../config/config.php';

use AI\Client\AiClient;
use AI\Config\AiClientConfig;
use AI\Request\GenericAiRequest;
use AI\AiException;

header('Content-Type: application/json');

try {
	$input = json_decode(file_get_contents("php://input"), true, 512, JSON_THROW_ON_ERROR);

	$result = [
		'endpoint' => null,
		'get' => [],      // Parametry pro GET část URL
		'body' => [],     // Parametry pro BODY curlu
		'prompt' => [],   // Data z formuláře
	];

	foreach ($input as $key => $value) {
		// Parsování __ai[...] parametrů
		if (preg_match('/^__ai\[(?<name>[^]]+)]$/', $key, $m)) {
			$name = $m['name'];

			if ($name === 'endpoint') {
				$result['endpoint'] = $value;
			} elseif (str_starts_with($name, 'get.')) {
				// get.web_id -> web_id do GET parametrů
				$result['get'][substr($name, 4)] = $value;
			} elseif (str_starts_with($name, 'body.')) {
				// body.client_id -> client_id do BODY
				$result['body'][substr($name, 5)] = $value;
			}
			continue;
		}

		// Ostatní jsou prompt data
		$result['prompt'][$key] = $value;
	}

	// Vytvoření requestu
	$request = GenericAiRequest::to($result['endpoint'])
		->withQueryParams($result['get'])
		->withBodyParams($result['body'])
		->withPrompt($result['prompt']);

	// var_dump($request->toPayload());
	// var_dump($result);

    // Konfigurace klienta
    $config = AiClientConfig::fromArray([
        'api_key' => OPENAI_API_KEY,
        'timeout' => 30,
        'max_retries' => 3,
        'debug' => defined('DEBUG_MODE') //&& DEBUG_MODE
    ]);

    $client = new AiClient($config);

    $response = $client->send($request);

    echo json_encode($response->toArray(), JSON_UNESCAPED_UNICODE);

} catch (AiException $e) {
    $statusCode = match ($e->getCode()) {
        AiException::ERROR_VALIDATION => 422,
        AiException::ERROR_INVALID_REQUEST => 400,
        AiException::ERROR_TIMEOUT => 504,
        default => 500
    };

    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'context' => $e->getContext()
    ], JSON_UNESCAPED_UNICODE);

} catch (\JsonException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Neplatný JSON vstup',
        'code' => 400
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
}