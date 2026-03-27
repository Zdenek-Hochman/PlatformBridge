<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\AI\API;

use Zoom\PlatformBridge\AI\Exception\AiException;
use Zoom\PlatformBridge\Config\ConfigManager;

/**
 * Registr a loader endpointů
 *
 * Slouží k:
 * - Registraci uživatelských endpointů z konfigurace (bridge-config.php → endpoints)
 * - Vyhledání správného endpointu podle názvu
 * - Lazy loading definic
 * - Injektování ConfigManager pro dynamické načítání required polí
 *
 * Uživatel definuje endpointy deklarativně v bridge-config.php jako pole:
 *
 *   'endpoints' => [
 *       'CreateSubject' => [
 *           'generator_id'  => 'subject',
 *           'response_type' => 'nested',
 *           'template'      => '/Components/NestedResult',
 *       ],
 *   ]
 *
 * Pro pokročilé případy je možné předat i FQCN třídy dědící z EndpointDefinition.
 */
class EndpointRegistry
{
    /** @var array<string, EndpointDefinition> Již vytvořené instance endpointů */
    private array $endpoints = [];

    /** @var array<string, class-string<EndpointDefinition>> Lazy-loaded třídy endpointů */
    private array $endpointClasses = [];

    /** @var array<string, array> Deklarativní konfigurace endpointů (pole parametrů) */
    private array $endpointConfigs = [];

    /** ConfigManager pro dynamické načítání pravidel z JSON */
    private ?ConfigManager $configManager = null;

	/** @var self|null Instance singletonu nebo null, pokud ještě nebyla vytvořena. */
    private static ?self $instance = null;

	/**
	 * Vrátí instanci singletonu EndpointRegistry.
	 *
	 * @return self Instance registru endpointů (singleton).
	 */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

	/**
	 * Resetuje singleton instanci (užitečné pro testování).
	 */
	public static function resetInstance(): void
	{
		self::$instance = null;
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
	 * Hromadně registruje endpointy z konfiguračního pole.
	 *
	 * Typicky se volá s hodnotou klíče 'endpoints' z bridge-config.php.
	 * Podporuje dva formáty registrace:
	 *
	 * 1. Deklarativní (doporučeno) — endpoint je konfigurace v poli:
	 *    'CreateSubject' => [
	 *        'generator_id'  => 'subject',
	 *        'response_type' => 'nested',
	 *        'template'      => '/Components/NestedResult',
	 *    ]
	 *
	 * 2. Třídou (pokročilé) — endpoint je FQCN třídy dědící z EndpointDefinition:
	 *    'CreateSubject' => \App\Endpoints\CreateSubjectEndpoint::class,
	 *
	 * @param array<string, array|class-string<EndpointDefinition>> $endpoints Mapa [název => konfigurace|třída]
	 * @return self Vrací aktuální instanci registru pro řetězení metod.
	 *
	 * @throws \InvalidArgumentException Pokud je konfigurace neplatná.
	 */
	public function registerFromConfig(array $endpoints): self
	{
		foreach ($endpoints as $name => $definition) {
			if (!is_string($name)) {
				throw new \InvalidArgumentException(
					"Neplatná konfigurace endpointu: klíč musí být string (název endpointu). Zkontrolujte 'endpoints' v bridge-config.php."
				);
			}

			// Formát 1: Deklarativní pole → ConfigurableEndpoint
			if (is_array($definition)) {
				$this->registerConfig($name, $definition);
				continue;
			}

			// Formát 2: Class-string → validace a lazy load
			if (is_string($definition)) {
				if (!class_exists($definition)) {
					throw new \InvalidArgumentException(
						"Třída endpointu '{$definition}' pro '{$name}' nebyla nalezena. "
						. "Zkontrolujte autoloading a zda soubor existuje."
					);
				}

				if (!is_subclass_of($definition, EndpointDefinition::class)) {
					throw new \InvalidArgumentException(
						"Třída '{$definition}' musí dědit ze Zoom\\PlatformBridge\\AI\\API\\EndpointDefinition."
					);
				}

				$this->registerClass($name, $definition);
				continue;
			}

			throw new \InvalidArgumentException(
				"Neplatná definice endpointu '{$name}': hodnota musí být pole (konfigurace) nebo string (FQCN třídy)."
			);
		}

		return $this;
	}

    /**
     * Registruje třídu endpointu (lazy loading)
     *
     * @param string $endpointName Název endpointu (např. 'CreateSubject')
     * @param class-string<EndpointDefinition> $className FQCN třídy endpointu
     */
    public function registerClass(string $endpointName, string $className): void
    {
        $this->endpointClasses[$endpointName] = $className;
    }

    /**
     * Registruje endpoint z konfiguračního pole (lazy loading).
     *
     * @param string $endpointName Název endpointu (např. 'CreateSubject')
     * @param array  $config       Konfigurační pole s parametry endpointu
     */
    public function registerConfig(string $endpointName, array $config): void
    {
        $this->endpointConfigs[$endpointName] = $config;
    }

    /**
     * Získá endpoint podle názvu
     */
    public function get(string $endpointName): ?EndpointDefinition
    {
		var_dump($this->endpoints); // Debug log

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

        // Lazy load z konfiguračního pole
        if (isset($this->endpointConfigs[$endpointName])) {
            $endpoint = new ConfigurableEndpoint($endpointName, $this->endpointConfigs[$endpointName]);

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
                "Neznámý endpoint: '{$endpointName}'. "
				. "Zkontrolujte, že je zaregistrován v 'endpoints' v bridge-config.php.",
                ['available' => $this->getAvailableEndpoints()]
            );
        }

        return $endpoint;
    }

    /**
     * Vrátí seznam dostupných endpointů
	 *
	 * @return string[] Názvy všech registrovaných endpointů.
     */
    public function getAvailableEndpoints(): array
    {
        return array_unique(array_merge(
            array_keys($this->endpoints),
            array_keys($this->endpointClasses),
            array_keys($this->endpointConfigs)
        ));
    }

    /**
     * Zjistí, zda je zaregistrován alespoň jeden endpoint.
     */
    public function hasEndpoints(): bool
    {
        return !empty($this->endpointClasses) || !empty($this->endpoints) || !empty($this->endpointConfigs);
    }
}
