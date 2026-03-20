<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Zoom\PlatformBridge\AI\Exception\AiException;
use Zoom\PlatformBridge\Config\ConfigManager;

/**
 * Registr a loader endpointů
 *
 * Slouží k:
 * - Registraci dostupných endpointů
 * - Vyhledání správného endpointu podle názvu
 * - Lazy loading definic
 * - Injektování ConfigManager pro dynamické načítání required polí
 */
class EndpointRegistry
{
    /** @var array<string, EndpointDefinition> */
    private array $endpoints = [];

    /** @var array<string, class-string<EndpointDefinition>> */
    private array $endpointClasses = [];

    /** ConfigManager pro dynamické načítání pravidel z JSON */
    private ?ConfigManager $configManager = null;

	/** @var self|null Instance singletonu nebo null, pokud ještě nebyla vytvořena.*/
    private static ?self $instance = null;

	/**
	 * Vrátí instanci singletonu EndpointRegistry.
	 * Pokud instance ještě neexistuje, vytvoří ji a zaregistruje výchozí endpointy.
	 *
	 * @return self Instance registru endpointů (singleton).
	 */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerDefaults();
        }
        return self::$instance;
    }

	/**
	 * Nastaví instanci ConfigManager pro registry a všechny endpointy.
	 *
	 * Umožňuje dynamické načítání required polí z JSON konfigurace.
	 * Aktualizuje již vytvořené endpointy, aby používaly nový ConfigManager.
	 *
	 * @param ConfigManager $configManager Instance správce konfigurace.
	 * @return self Vrací aktuální instanci registru pro řetězení metod.
	 */
    public function setConfigManager(ConfigManager $configManager): self
    {
        $this->configManager = $configManager;

        // Aktualizuj již vytvořené endpointy
        foreach ($this->endpoints as $endpoint) {
            $endpoint->setConfigManager($configManager);
        }

        return $this;
    }

	/**
	 * Registruje výchozí endpointy do registru.
	 *
	 * Přidává základní endpointy jako lazy-loaded třídy pro pozdější použití.
	 * Slouží k inicializaci registru s předdefinovanými endpointy.
	 *
	 * @return void
	 */
    private function registerDefaults(): void
    {
        // Registruj všechny endpointy jako lazy-loaded
        $this->registerClass('CreateSubject', Endpoints\ZL\CreateSubjectEndpoint::class);
    }

    /**
     * Registruje třídu endpointu (lazy loading)
     *
     * @param class-string<EndpointDefinition> $className
     */
    public function registerClass(string $endpointName, string $className): void
    {
        $this->endpointClasses[$endpointName] = $className;
    }

    /**
     * Získá endpoint podle názvu
     */
    public function get(string $endpointName): ?EndpointDefinition
    {
        // Nejdřív zkus již vytvořenou instanci
        if (isset($this->endpoints[$endpointName])) {
            return $this->endpoints[$endpointName];
        }

        // Lazy load z registrované třídy
        if (isset($this->endpointClasses[$endpointName])) {
            $className = $this->endpointClasses[$endpointName];
            $endpoint = new $className();

			// Injektuj ConfigManager pokud je k dispozici
            if ($this->configManager !== null) {
                $endpoint->setConfigManager($this->configManager);
            }

            $this->endpoints[$endpointName] = $endpoint;
            return $endpoint;
        }

        return null;
    }

    /**
     * Získá endpoint nebo vyhodí výjimku
     */
    public function getOrFail(string $endpointName): EndpointDefinition
    {
        $endpoint = $this->get($endpointName);

        if ($endpoint === null) {
            throw AiException::invalidRequest(
                "Neznámý endpoint: {$endpointName}",
                ['available' => $this->getAvailableEndpoints()]
            );
        }

        return $endpoint;
    }

    /**
     * Vrátí seznam dostupných endpointů
     */
    public function getAvailableEndpoints(): array
    {
        return array_unique(array_merge(
            array_keys($this->endpoints),
            array_keys($this->endpointClasses)
        ));
    }
}
