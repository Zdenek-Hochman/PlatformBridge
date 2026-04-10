<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\Paths\PathResolver;
use PlatformBridge\AI\Exception\AiException;
use PlatformBridge\Translator\Translator;
use PlatformBridge\Config\{ConfigLoader, ConfigManager, ConfigValidator};

/**
 * Resolvne správný endpoint z parametrů požadavku.
 *
 * Vytvoří překladač + config stack a nakonfiguruje EndpointRegistry.
 * Zpracovává také __generate_key pro single-key mód.
 */
final class EndpointResolver
{
    public function __construct(
        private readonly PathResolver $paths,
    ) {}

    public function resolve(array $params, array &$input): EndpointDefinition
    {
        $name = $params['config']['endpoint'] ?? throw AiException::invalidRequest('Chybí název endpointu.');

        $configManager = $this->buildConfigManager($params);

        $registry = EndpointRegistry::getInstance();
        $registry->setConfigManager($configManager);

        $endpoint = $registry->getOrFail($name);

        $this->applySingleKeyMode($endpoint, $input);

        return $endpoint;
    }

    // ── Interní ─────────────────────────────────────────────────

    private function buildConfigManager(array $params): ConfigManager
    {
        $configPath = $params['config']['config_path'] ?? $this->paths->configPath();
        $locale = $params['config']['locale'] ?? 'cs';

        $translator = Translator::create(
            locale: $locale,
            langPath: $this->paths->langPath(),
        );

        $loader = new ConfigLoader(
            $configPath,
            $this->paths,
            new ConfigValidator(),
            $translator->getVariableResolver(),
        );

        return new ConfigManager($loader);
    }

    private function applySingleKeyMode(EndpointDefinition $endpoint, array &$input): void
    {
        $generateKey = $input['__generate_key'] ?? null;
        unset($input['__generate_key']);

        if (is_string($generateKey) && $generateKey !== '') {
            $endpoint->setSingleKeyMode($generateKey);
        }
    }
}
