<?php

declare(strict_types=1);

namespace PlatformBridge\AI\API\Core\Endpoint;

use PlatformBridge\AI\Exception\AiException;
use PlatformBridge\Config\ConfigManager;

/**
 * Registr a loader endpointů (singleton).
 *
 * - Registrace uživatelských endpointů z konfigurace (bridge-config.php → endpoints)
 * - Vyhledání správného endpointu podle názvu
 * - Lazy-loading definic (třídy i konfigurace)
 * - Injektování ConfigManager pro dynamické načítání required polí
 *
 * Doporučený způsob — třída s atributem (bez pojmenovaného klíče):
 *
 *   'endpoints' => [
 *       CreateSubjectEndpoint::class,
 *       ['class' => AnotherEndpoint::class, 'file' => __DIR__ . '/../AnotherEndpoint.php'],
 *   ]
 */
class EndpointRegistry
{
    /** @var array<string, EndpointDefinition> Již vytvořené instance */
    private array $endpoints = [];

    /** @var array<string, class-string<EndpointDefinition>> Lazy-loaded třídy */
    private array $endpointClasses = [];

    /** @var array<string, array> Deklarativní konfigurace */
    private array $endpointConfigs = [];

    /** @var array<string, callable> Callable transformace čekající na injekci */
    private array $pendingTransforms = [];

    private ?ConfigManager $configManager = null;
    private EndpointFactory $factory;
    private RegistrationParser $parser;

    private static ?self $instance = null;

    private function __construct()
    {
        $this->factory = new EndpointFactory();
        $this->parser = new RegistrationParser();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── ConfigManager ───────────────────────────────────────────

    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;
        $this->factory->setConfigManager($configManager);

        foreach ($this->endpoints as $endpoint) {
            $endpoint->setConfigManager($configManager);
        }

        return $this;
    }

    // ── Registrace ──────────────────────────────────────────────

    public function registerFromConfig(array $endpoints): self
    {
        foreach ($this->parser->parse($endpoints) as $entry) {
            if ($entry['type'] === 'config') {
                $this->endpointConfigs[$entry['name']] = $entry['config'];
            } else {
                $this->endpointClasses[$entry['name']] = $entry['className'];

                if ($entry['transform'] !== null) {
                    $this->pendingTransforms[$entry['name']] = $entry['transform'];
                }
            }
        }

        return $this;
    }

    public function registerClass(string $name, string $className): void
    {
        $this->endpointClasses[$name] = $className;
    }

    public function registerConfig(string $name, array $config): void
    {
        $this->endpointConfigs[$name] = $config;
    }

    // ── Lookup ──────────────────────────────────────────────────

    public function get(string $name): ?EndpointDefinition
    {
        if (isset($this->endpoints[$name])) {
            return $this->endpoints[$name];
        }

        if (isset($this->endpointClasses[$name])) {
            $transform = $this->pendingTransforms[$name] ?? null;
            unset($this->pendingTransforms[$name]);

            return $this->endpoints[$name] = $this->factory->fromClass($this->endpointClasses[$name], $transform);
        }

        if (isset($this->endpointConfigs[$name])) {
            return $this->endpoints[$name] = $this->factory->fromConfig($name, $this->endpointConfigs[$name]);
        }

        return null;
    }

    public function getOrFail(string $name): EndpointDefinition
    {
        return $this->get($name) ?? throw AiException::invalidRequest(
            "Neznámý endpoint: '{$name}'. "
            . "Zkontrolujte, že je zaregistrován v 'endpoints' v bridge-config.php.",
            ['available' => $this->getAvailableEndpoints()],
        );
    }

    public function getAvailableEndpoints(): array
    {
        return array_unique(array_merge(
            array_keys($this->endpoints),
            array_keys($this->endpointClasses),
            array_keys($this->endpointConfigs),
        ));
    }

    public function hasEndpoints(): bool
    {
        return $this->endpoints !== []
            || $this->endpointClasses !== []
            || $this->endpointConfigs !== [];
    }
}
