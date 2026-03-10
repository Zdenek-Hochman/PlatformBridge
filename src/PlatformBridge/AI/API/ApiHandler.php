<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

/**
 * API Handler - Jádro systému pro zpracování AI požadavků.
 *
 * Zpracovává příchozí requesty, ověřuje podpisy a deleguje
 * na registrované endpointy. Podporuje jak standalone (localhost),
 * tak vendor (produkce) režim.
 *
 * Použití:
 *   // Automatická detekce cest:
 *   ApiHandler::bootstrap()->handle();
 *
 *   // Ruční konfigurace:
 *   ApiHandler::create('/path/to/bridge-config.php', '/path/to/config/defaults')->handle();
 *
 * @see EndpointRegistry
 * @see EndpointDefinition
 */
final class ApiHandler
{
    private array $bridgeConfig;

    /**
     * @param string $configPath  Cesta k bridge-config.php (obsahuje secretKey, api_key, ...)
     * @param string $configDir   Cesta ke složce s JSON konfiguracemi (blocks.json, generators.json, ...)
     */
    private function __construct(
        private readonly string $configPath,
        private readonly string $configDir,
    ) {
        $this->loadConfig();
    }

    /**
     * Tovární metoda s explicitními cestami.
     *
     * @param string $configPath  Cesta k bridge-config.php
     * @param string $configDir   Cesta ke složce s JSON konfiguracemi
     */
    public static function create(string $configPath, string $configDir): self
    {
        return new self($configPath, $configDir);
    }

    /**
     * Bootstrap s automatickou detekcí cest.
     *
     * Detekuje zda balíček běží ze vendor/ nebo standalone,
     * a podle toho nastaví cesty ke konfiguraci.
     *
     * Priorita konfigurace:
     *   1. {projectRoot}/config/bridge-config.php
     *   2. {packageRoot}/resources/config/bridge-config.php
     *
     * @param string|null $configPath  Volitelná explicitní cesta k bridge-config.php
     * @param string|null $configDir   Volitelná explicitní cesta ke konfiguračním JSON souborům
     */
    public static function bootstrap(?string $configPath = null, ?string $configDir = null): self
    {
        $packageRoot = self::detectPackageRoot();
        $projectRoot = self::detectProjectRoot($packageRoot);

        // Resolve config path
        if ($configPath === null) {
            // Nejdříve zkus project-level config
            $projectConfig = $projectRoot . DIRECTORY_SEPARATOR . 'config'
                . DIRECTORY_SEPARATOR . 'bridge-config.php';

            if (file_exists($projectConfig)) {
                $configPath = $projectConfig;
            } else {
                // Fallback na package config
                $configPath = $packageRoot . DIRECTORY_SEPARATOR . 'resources'
                    . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bridge-config.php';
            }
        }

        // Resolve config dir
        if ($configDir === null) {
            $configDir = $packageRoot . DIRECTORY_SEPARATOR . 'resources'
                . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults';
        }

        return new self($configPath, $configDir);
    }

    /**
     * Detekuje kořenový adresář balíčku.
     *
     * Tento soubor je v: src/PlatformBridge/AI/API/ApiHandler.php
     * Package root = 4 úrovně výš.
     */
    private static function detectPackageRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Detekuje kořenový adresář projektu (hostující aplikace).
     *
     * Pokud jsme ve vendor, project root je 3 úrovně nad package root.
     * Jinak package root = project root (standalone).
     */
    private static function detectProjectRoot(string $packageRoot): string
    {
        $vendorAutoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';

        if (file_exists($vendorAutoload)) {
            return dirname($packageRoot, 3);
        }

        return $packageRoot;
    }

    /**
     * Načte bridge konfiguraci.
     *
     * @throws \RuntimeException Pokud konfigurační soubor neexistuje
     */
    private function loadConfig(): void
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException("Configuration file not found: {$this->configPath}");
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $this->configPath;

        if (!is_array($config)) {
            throw new \RuntimeException("Bridge config must return an array.");
        }

        $this->bridgeConfig = $config;
    }

    /**
     * Zpracuje příchozí HTTP požadavek.
     *
     * Flow:
     *   1. Parse JSON vstupu
     *   2. Ověření HMAC podpisu
     *   3. Routing na endpoint
     *   4. Sestavení AI requestu
     *   5. Odeslání na AI provider
     *   6. Parsování a renderování odpovědi
     */
    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $this->processRequest();
        } catch (\Zoom\PlatformBridge\Security\SecurityException $e) {
            $this->sendSecurityError($e);
        } catch (\Zoom\PlatformBridge\AI\AiException $e) {
            $this->sendAiError($e);
        } catch (\JsonException $e) {
            $this->sendJsonError($e);
        } catch (\Throwable $e) {
            $this->sendInternalError($e);
        }
    }

    /**
     * Hlavní logika zpracování requestu.
     */
    private function processRequest(): void
    {
        // 1) Načtení JSON vstupu
        $input = json_decode(
            file_get_contents("php://input"),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        // 2) Validace konfigurace
        $secretKey = $this->bridgeConfig['secretKey'] ?? null;
        $ttl = $this->bridgeConfig['ttl'] ?? null;

        if (!$secretKey) {
            throw new \RuntimeException('Secret key not configured.');
        }

        // 3) ConfigManager pro JSON konfigurace
        $configManager = \Zoom\PlatformBridge\Config\ConfigManager::create($this->configDir);

        // 4) Ověření HMAC podpisu
        if (!isset($input['__ai_signed'])) {
            throw new \Zoom\PlatformBridge\Security\SecurityException(
                'Missing signed params (__ai_signed).'
            );
        }

        $signedParams = new \Zoom\PlatformBridge\Security\SignedParams($secretKey, $ttl);
        $verifiedParams = $signedParams->verify($input['__ai_signed']);
        unset($input['__ai_signed']);

        // 5) Získání endpointu z registru
        $endpointName = $verifiedParams['config']['endpoint'] ?? null;

        if (!$endpointName) {
            throw \Zoom\PlatformBridge\AI\AiException::invalidRequest(
                'Chybí název endpointu v konfiguraci.'
            );
        }

        $registry = EndpointRegistry::getInstance();
        $registry->setConfigManager($configManager);
        $endpointDef = $registry->getOrFail($endpointName);

        // 6) Detekce single-key módu (__generate_key)
        $generateKey = $input['__generate_key'] ?? null;
        unset($input['__generate_key']);

        if ($generateKey !== null && is_string($generateKey) && $generateKey !== '') {
            $endpointDef->setSingleKeyMode($generateKey);
        }

        // 7) Sestavení requestu přes EndpointDefinition
        $request = $endpointDef->createRequest(
            $input,
            $verifiedParams['get'] ?? [],
            $verifiedParams['body'] ?? [],
        );

        // Přidání extra headers
        foreach (($verifiedParams['headers'] ?? []) as $name => $value) {
            $request->withHeader($name, $value);
        }

        // 8) AiClient + odeslání
        $clientConfig = \Zoom\PlatformBridge\AI\AiClientConfig::fromArray([
            'api_key'     => $this->bridgeConfig['api_key'] ?? "YOUR_API_KEY_HERE",
            'timeout'     => $this->bridgeConfig['timeout'] ?? 30,
            'max_retries' => $this->bridgeConfig['max_retries'] ?? 3,
            'debug'       => defined('DEBUG_MODE'),
        ]);

        $client = new \Zoom\PlatformBridge\AI\AiClient($clientConfig);
        $response = $client->send($request);

        // 9) Parsování odpovědi
        $parsedData = $endpointDef->parseResponse($response->getResponse());

        // 10) Renderování HTML
        $renderer = \Zoom\PlatformBridge\AI\AiResponseRenderer::create();
        $html = $renderer->render($parsedData, $endpointDef->getActiveTemplate(), [
            'variant'       => $endpointDef->detectVariant($input),
            'response_type' => $endpointDef->getActiveResponseType(),
            'single_key'    => $endpointDef->getSingleKey(),
        ]);

        $responseArray = $response->toArray();

        // Odpověď
        echo json_encode([
            "api" => [
                'success'     => $responseArray['success'],
                'status_code' => $responseArray['status_code'],
                'meta'        => $responseArray['meta'],
            ],
            "provider" => [
                'success'     => true,
                'status_code' => 200,
                'meta'        => [
                    'endpoint'      => $endpointName,
                    'response_type' => $endpointDef->getActiveResponseType(),
                    'single_key'    => $endpointDef->getSingleKey(),
                ],
            ],
            "data" => [
                "raw"    => $responseArray['response'],
                'parsed' => $parsedData,
                'html'   => $html,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // ERROR RESPONSES
    // =========================================================================

    private function sendSecurityError(\Zoom\PlatformBridge\Security\SecurityException $e): void
    {
        http_response_code(403);
        echo json_encode([
            "api" => [
                "success"     => false,
                "status_code" => 403,
                "error"       => [
                    "type"    => "security",
                    "message" => "Forbidden",
                    "code"    => 403,
                ],
            ],
            "provider" => null,
            "data"     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendAiError(\Zoom\PlatformBridge\AI\AiException $e): void
    {
        $statusCode = match ($e->getCode()) {
            \Zoom\PlatformBridge\AI\AiException::ERROR_VALIDATION     => 422,
            \Zoom\PlatformBridge\AI\AiException::ERROR_INVALID_REQUEST => 400,
            \Zoom\PlatformBridge\AI\AiException::ERROR_TIMEOUT         => 504,
            default                                                     => 500,
        };

        http_response_code($statusCode);
        echo json_encode([
            "api" => [
                "success"     => false,
                "status_code" => $e->getCode(),
                "error"       => [
                    "type"    => "ai_provider",
                    "message" => $e->getMessage(),
                    "context" => $e->getContext(),
                    "code"    => $e->getCode(),
                ],
            ],
            "provider" => null,
            "data"     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendJsonError(\JsonException $e): void
    {
        http_response_code(400);
        echo json_encode([
            "api" => [
                "success"     => false,
                "status_code" => 400,
                "error"       => [
                    "type"    => "invalid_json",
                    "message" => "Neplatný JSON vstup",
                    "context" => null,
                    "code"    => 400,
                ],
            ],
            "provider" => null,
            "data"     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function sendInternalError(\Throwable $e): void
    {
        http_response_code(500);
        echo json_encode([
            "api" => [
                "success"     => false,
                "status_code" => 500,
                "error"       => [
                    "type"    => "internal_error",
                    "message" => $e->getMessage(),
                    "context" => $e->getTraceAsString(),
                    "code"    => 500,
                ],
            ],
            "provider" => null,
            "data"     => null,
        ], JSON_UNESCAPED_UNICODE);
    }
}
