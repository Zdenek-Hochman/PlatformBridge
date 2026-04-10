<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core;

use PlatformBridge\AI\API\Core\Endpoint\{EndpointRegistry, EndpointResolver};
use PlatformBridge\AI\API\Core\Response\ApiResponseBuilder;
use PlatformBridge\AI\Exception\{AiException, JsonException};
use PlatformBridge\Paths\{PathResolver, PathResolverFactory};

/**
 * Vstupní bod pro zpracování příchozích AI požadavků.
 *
 * Podporuje standalone (localhost) i vendor (produkce) režim.
 * Konfigurace se načítá z bridge-config.php a security-config.php.
 */
final class ApiHandler
{
    private array $config;
    private array $securityConfig;
    private AiRequestProcessor $processor;

    private function __construct(
        private readonly string $configPath,
        private readonly string $securityConfigPath,
        private readonly PathResolver $paths,
    ) {
        $this->config = self::loadConfig($this->configPath, 'Bridge');
        $this->securityConfig = self::loadConfig($this->securityConfigPath, 'Security');

        $this->registerUserEndpoints();

        $this->processor = new AiRequestProcessor(
            $this->securityConfig,
            $this->config,
            new EndpointResolver($this->paths),
            new ApiResponseBuilder($this->paths),
        );
    }

    public static function bootstrap(): self
    {
        $paths = PathResolverFactory::auto(dirname(__DIR__, 5));

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        return new self(
            $paths->bridgeConfigFile(),
            $paths->securityConfigFile(),
            $paths,
        );
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = $this->parseInput();
            $result = $this->processor->process($input);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            ApiErrorHandler::sendError($e);
        }
    }

    public function getPathResolver(): PathResolver
    {
        return $this->paths;
    }

    // ── Interní ─────────────────────────────────────────────────

    private function registerUserEndpoints(): void
    {
        $endpoints = $this->config['endpoints'] ?? [];

        if (!empty($endpoints) && is_array($endpoints)) {
            EndpointRegistry::getInstance()->registerFromConfig($endpoints);
        }
    }

    private function parseInput(): array
    {
        try {
            return json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw JsonException::invalidJson('Nepodařilo se zpracovat odeslaná data.', $e, $e->getTrace());
        }
    }

    /**
     * @throws AiException Pokud soubor neexistuje nebo nevrací pole
     */
    private static function loadConfig(string $path, string $label): array
    {
        if (!file_exists($path)) {
            throw AiException::invalidRequest("{$label} config not found: {$path}");
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $path;

        if (!is_array($config)) {
            throw AiException::invalidRequest("{$label} config must return an array.");
        }

        return $config;
    }
}