<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Zoom\PlatformBridge\AI\AiClient;
use Zoom\PlatformBridge\AI\AiClientConfig;
use Zoom\PlatformBridge\AI\AiException;
use Zoom\PlatformBridge\AI\AiResponse;
use Zoom\PlatformBridge\AI\AiResponseRenderer;
use Zoom\PlatformBridge\Config\ConfigLoader;
use Zoom\PlatformBridge\Config\ConfigManager;
use Zoom\PlatformBridge\Config\ConfigValidator;
use Zoom\PlatformBridge\Config\PathResolver;
use Zoom\PlatformBridge\Security\SecurityException;
use Zoom\PlatformBridge\Security\SignedParams;

/**
 * API Handler – zpracování příchozích AI požadavků.
 *
 * Podporuje standalone (localhost) i vendor (produkce) režim.
 * Konfigurace se načítá vždy z resources/config/bridge-config.php
 * (package-level), nebo z config/bridge-config.php (project-level).
 *
 * Použití:
 *   ApiHandler::bootstrap()->handle();
 *   ApiHandler::create($configPath, $configDir)->handle();
 */
final class ApiHandler
{
    private array $config;

    private function __construct(
        private readonly string $configPath,
        private readonly string $configDir,
    ) {
        $this->config = $this->loadConfig();
    }

    /**
     * Tovární metoda s explicitními cestami.
     */
    public static function create(string $configPath, string $configDir): self
    {
        return new self($configPath, $configDir);
    }

    /**
     * Bootstrap s automatickou detekcí cest přes PathResolver.
     */
    public static function bootstrap(): self
    {
        $paths = new PathResolver();

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $configPath = $paths->resolvedBridgeConfigFile();
        $configDir = $paths->resolvedConfigPath();

        return new self($configPath, $configDir);
    }

    // ─── Request handling ───────────────────────────────────────

    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $this->processRequest();
        } catch (SecurityException) {
            self::sendError(403, 'security', 'Forbidden');
        } catch (AiException $e) {
            self::sendAiError($e);
        } catch (\JsonException) {
            self::sendError(400, 'invalid_json', 'Neplatný JSON vstup');
        } catch (\Throwable $e) {
            self::sendError(500, 'internal_error', $e->getMessage(), $e->getTraceAsString());
        }
    }

    private function processRequest(): void
    {
        $input    = $this->parseInput();
        $params   = $this->verifySignature($input);
        $endpoint = $this->resolveEndpoint($params, $input);
        $response = $this->callAiProvider($endpoint, $input, $params);

        $this->sendSuccessResponse($response, $endpoint, $input, $params);
    }

    // ─── Pipeline kroky ─────────────────────────────────────────

    private function parseInput(): array
    {
        return json_decode(
            file_get_contents('php://input'),
            true, 512, JSON_THROW_ON_ERROR,
        );
    }

    private function verifySignature(array &$input): array
    {
        $secretKey = $this->config['secretKey']
            ?? throw new \RuntimeException('Secret key not configured.');

        if (!isset($input['__ai_signed'])) {
            throw new SecurityException('Missing signed params (__ai_signed).');
        }

        $verified = (new SignedParams($secretKey, $this->config['ttl'] ?? null))
            ->verify($input['__ai_signed']);

        unset($input['__ai_signed']);

        return $verified;
    }

    private function resolveEndpoint(array $params, array &$input): EndpointDefinition
    {
        $name = $params['config']['endpoint']
            ?? throw AiException::invalidRequest('Chybí název endpointu v konfiguraci.');

        $paths = new PathResolver();
        $loader = new ConfigLoader(
            $paths->userConfigPath(),
            $paths->packageDefaultsPath(),
            new ConfigValidator(),
        );

        $registry = EndpointRegistry::getInstance();
        $registry->setConfigManager(new ConfigManager($loader));

        $endpoint = $registry->getOrFail($name);

        // Single-key mód
        $generateKey = $input['__generate_key'] ?? null;
        unset($input['__generate_key']);

        if (is_string($generateKey) && $generateKey !== '') {
            $endpoint->setSingleKeyMode($generateKey);
        }

        return $endpoint;
    }

    private function callAiProvider(
        EndpointDefinition $endpoint,
        array $input,
        array $params,
    ): AiResponse {
        $request = $endpoint->createRequest(
            $input,
            $params['get'] ?? [],
            $params['body'] ?? [],
        );

        foreach ($params['headers'] ?? [] as $name => $value) {
            $request->withHeader($name, $value);
        }

        $client = new AiClient(AiClientConfig::fromArray([
            'api_key'     => $this->config['api_key'] ?? 'YOUR_API_KEY_HERE',
            'timeout'     => $this->config['timeout'] ?? 30,
            'max_retries' => $this->config['max_retries'] ?? 3,
            'base_url'    => $this->config['base_url'] ?? '',
            'debug'       => defined('DEBUG_MODE'),
        ]));

        return $client->send($request);
    }

    // ─── Odpovědi ───────────────────────────────────────────────

    private function sendSuccessResponse(
        AiResponse $response,
        EndpointDefinition $endpoint,
        array $input,
        array $params,
    ): void {
        $parsed = $endpoint->parseResponse($response->getResponse());

        $html = AiResponseRenderer::create()->render(
            $parsed,
            $endpoint->getActiveTemplate(),
            [
                'variant'       => $endpoint->detectVariant($input),
                'response_type' => $endpoint->getActiveResponseType(),
                'single_key'    => $endpoint->getSingleKey(),
            ],
        );

        $data = $response->toArray();

        echo json_encode([
            'api' => [
                'success'     => $data['success'],
                'status_code' => $data['status_code'],
                'meta'        => $data['meta'],
            ],
            'provider' => [
                'success'     => true,
                'status_code' => 200,
                'meta'        => [
                    'endpoint'      => $params['config']['endpoint'] ?? 'unknown',
                    'response_type' => $endpoint->getActiveResponseType(),
                    'single_key'    => $endpoint->getSingleKey(),
                ],
            ],
            'data' => [
                'raw'    => $data['response'],
                'parsed' => $parsed,
                'html'   => $html,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    // ─── Konfigurace ────────────────────────────────────────────

    private function loadConfig(): array
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException("Config not found: {$this->configPath}");
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $this->configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('Bridge config must return an array.');
        }

        return $config;
    }

    // ─── Chybové odpovědi ───────────────────────────────────────

    private static function sendError(
        int $status,
        string $type,
        string $message,
        ?string $context = null,
    ): void {
        http_response_code($status);
        echo json_encode([
            'api' => [
                'success'     => false,
                'status_code' => $status,
                'error'       => [
                    'type'    => $type,
                    'message' => $message,
                    'context' => $context,
                    'code'    => $status,
                ],
            ],
            'provider' => null,
            'data'     => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function sendAiError(AiException $e): void
    {
        $status = match ($e->getCode()) {
            AiException::ERROR_VALIDATION      => 422,
            AiException::ERROR_INVALID_REQUEST => 400,
            AiException::ERROR_TIMEOUT         => 504,
            default                            => 500,
        };

        http_response_code($status);
        echo json_encode([
            'api' => [
                'success'     => false,
                'status_code' => $e->getCode(),
                'error'       => [
                    'type'    => 'ai_provider',
                    'message' => $e->getMessage(),
                    'context' => $e->getContext(),
                    'code'    => $e->getCode(),
                ],
            ],
            'provider' => null,
            'data'     => null,
        ], JSON_UNESCAPED_UNICODE);
    }
}
