<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\AI\API\Types\Attributes\AttributeEndpoint;
use PlatformBridge\AI\API\Types\Configurable\ConfigurableEndpoint;
use PlatformBridge\Config\ConfigManager;

/**
 * Továrna pro vytváření instancí endpointů.
 *
 * Podporuje dva režimy:
 * - Třídové endpointy (extends EndpointDefinition / AttributeEndpoint)
 * - Konfigurovatelné endpointy (deklarativní pole → ConfigurableEndpoint)
 */
final class EndpointFactory
{
    public function __construct(
        private ?ConfigManager $configManager = null,
    ) {}

    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;
        return $this;
    }

    public function fromClass(string $className, ?callable $transform = null): EndpointDefinition
    {
        $endpoint = new $className();

        $this->injectConfigManager($endpoint);

        if ($transform !== null && $endpoint instanceof AttributeEndpoint) {
            $endpoint->setTransform($transform);
        }

        return $endpoint;
    }

    public function fromConfig(string $name, array $config): ConfigurableEndpoint
    {
        $endpoint = new ConfigurableEndpoint($name, $config);

        $this->injectConfigManager($endpoint);

        return $endpoint;
    }

    private function injectConfigManager(EndpointDefinition $endpoint): void
    {
        if ($this->configManager !== null) {
            $endpoint->setConfigManager($this->configManager);
        }
    }
}
