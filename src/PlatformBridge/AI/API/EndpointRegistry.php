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
 * - Čtení názvu endpointu z #[Endpoint] atributu (auto-discovery)
 *
 * Doporučený způsob — třída s atributem (bez pojmenovaného klíče):
 *
 *   'endpoints' => [
 *       CreateSubjectEndpoint::class,
 *       ['class' => AnotherEndpoint::class, 'file' => __DIR__ . '/../AnotherEndpoint.php'],
 *   ]
 *
 * Legacy způsob — deklarativní pole s pojmenovaným klíčem:
 *
 *   'endpoints' => [
 *       'CreateSubject' => [
 *           'generator_id'  => 'subject',
 *           'response_type' => 'nested',
 *           'template'      => '/Components/NestedResult',
 *       ],
 *   ]
 */
class EndpointRegistry
{
    /** @var array<string, EndpointDefinition> Již vytvořené instance endpointů */
    private array $endpoints = [];

    /** @var array<string, class-string<EndpointDefinition>> Lazy-loaded třídy endpointů */
    private array $endpointClasses = [];

    /** @var array<string, array> Deklarativní konfigurace endpointů (pole parametrů) */
    private array $endpointConfigs = [];

    /** @var array<string, callable> Callable transformace čekající na injekci do endpointů */
    private array $pendingTransforms = [];

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
	 * Podporuje tři formáty registrace:
	 *
	 * Podporované formáty (od nejjednoduššího po nejflexibilnější):
	 *
	 * ── Formát A: Třída s #[Endpoint] atributem (DOPORUČENO) ──
	 * Název endpointu se automaticky načte z atributu. Numerický klíč.
	 *
	 *   CreateSubjectEndpoint::class,
	 *
	 * ── Formát B: Třída s atributem + soubor (bez autoloadingu) ──
	 *   ['class' => CreateSubjectEndpoint::class, 'file' => __DIR__ . '/../CreateSubjectEndpoint.php'],
	 *
	 * ── Formát C: Třída s atributem + runtime transform override ──
	 *   ['class' => CreateSubjectEndpoint::class, 'transform' => fn(array $input, mixed ...$ctx) => $input],
	 *
	 * ── Formát D: Deklarativní pole (bez vlastní třídy) ──
	 *   'CreateSubject' => [
	 *       'generator_id'  => 'subject',
	 *       'response_type' => 'nested',
	 *       'template'      => '/Components/NestedResult',
	 *   ],
	 *
	 * ── Formát E: Pojmenovaný klíč + class-string ──
	 *   'CreateSubject' => \App\Endpoints\CreateSubjectEndpoint::class,
	 *
	 * Formáty A–C využívají #[Endpoint] atribut na třídě dědící z AttributeEndpoint.
	 * Název endpointu se z atributu načte automaticky — nepotřebujete pojmenovaný klíč.
	 *
	 * @param array $endpoints Mapa/seznam registrací endpointů
	 * @return self Vrací aktuální instanci registru pro řetězení metod.
	 *
	 * @throws \InvalidArgumentException Pokud je konfigurace neplatná.
	 */
	public function registerFromConfig(array $endpoints): self
	{
		foreach ($endpoints as $name => $definition) {
			// ── Formát A: Numerický klíč + class-string (atribut) ──
			// Třída musí dědit z AttributeEndpoint a mít #[Endpoint('NázevEndpointu')] atribut.
			// Název endpointu se automaticky načte z atributu. Vyžaduje funkční autoloading.
			//
			// Struktura v bridge-config.php:
			//   'endpoints' => [
			//       CreateSubjectEndpoint::class,
			//       AnotherEndpoint::class,
			//   ]
			//
			// → $name = 0 (int), $definition = 'App\Endpoints\CreateSubjectEndpoint' (string)
			// → Výsledek: registerAttributeClass() → název z #[Endpoint], třída jako AttributeEndpoint
			if (is_int($name) && is_string($definition)) {
				$this->registerAttributeClass($definition);
				continue;
			}

			// ── Formát B/C: Numerický klíč + pole s 'class' (atribut + file/transform) ──
			// Stejné jako Formát A, ale s možností:
			//   - 'file'      → require_once souboru (pro prostředí bez autoloadingu)
			//   - 'transform' → runtime override transformace (callable)
			//
			// Struktura v bridge-config.php:
			//   'endpoints' => [
			      // B: Třída + soubor (bez autoloadingu)
			//       ['class' => CreateSubjectEndpoint::class, 'file' => __DIR__ . '/../CreateSubjectEndpoint.php'],
			//
			      // C: Třída + runtime transform override
			//       ['class' => CreateSubjectEndpoint::class, 'transform' => fn(array $input, mixed ...$ctx) => $input],
			//
			      // B+C: Třída + soubor + transform
			//       [
			//           'class'     => CreateSubjectEndpoint::class,
			//           'file'      => __DIR__ . '/../CreateSubjectEndpoint.php',
			//           'transform' => fn(array $input, mixed ...$ctx) => modifyInput($input),
			//       ],
			//   ]
			//
			// → $name = 0 (int), $definition = ['class' => '...', 'file' => '...', 'transform' => fn()]
			// → Výsledek: registerAttributeClassFromArray() → název z #[Endpoint], třída jako AttributeEndpoint
			if (is_int($name) && is_array($definition) && isset($definition['class'])) {
				$this->registerAttributeClassFromArray($definition);
				continue;
			}

			// ── Od tohoto bodu vyžadujeme pojmenovaný klíč (string) ──
			// Pokud je klíč stále int a nedošlo k matchnutí výše, konfigurace je neplatná.
			if (!is_string($name)) {
				throw new \InvalidArgumentException(
					"Neplatná konfigurace endpointu: pro deklarativní konfiguraci musí být klíč string (název endpointu). "
					. "Pro třídy s #[Endpoint] atributem použijte numerický index. Zkontrolujte 'endpoints' v bridge-config.php."
				);
			}

			// ── Formát D: Deklarativní pole → ConfigurableEndpoint ──
			// Nevyžaduje vlastní třídu — endpoint se sestaví z konfiguračního pole.
			// Název endpointu = klíč pole. Vytvoří instanci ConfigurableEndpoint.
			//
			// Struktura v bridge-config.php:
			//   'endpoints' => [
			//       'CreateSubject' => [
			//           'generator_id'  => 'subject',
			//           'response_type' => 'nested',        // 'nested' | 'simple' | 'stream'
			//           'template'      => '/Components/NestedResult',
			//           'required'      => ['topic', 'style'],  // volitelně, jinak se načte z JSON
			//       ],
			//       'GenerateTitle' => [
			//           'generator_id'  => 'title',
			//           'response_type' => 'simple',
			//           'template'      => '/Components/SimpleResult',
			//       ],
			//   ]
			//
			// → $name = 'CreateSubject' (string), $definition = ['generator_id' => '...', ...] (array)
			// → Výsledek: registerConfig() → ConfigurableEndpoint s daným názvem a konfigurací
			if (is_array($definition)) {
				$this->registerConfig($name, $definition);
				continue;
			}

			// ── Formát E: Pojmenovaný klíč + class-string ──
			// Třída musí dědit z EndpointDefinition (ne nutně AttributeEndpoint).
			// Název endpointu = klíč pole (ne z atributu). Vyžaduje funkční autoloading.
			//
			// Struktura v bridge-config.php:
			//   'endpoints' => [
			//       'CreateSubject' => \App\Endpoints\CreateSubjectEndpoint::class,
			//       'GenerateTitle' => \App\Endpoints\GenerateTitleEndpoint::class,
			//   ]
			//
			// → $name = 'CreateSubject' (string), $definition = 'App\Endpoints\CreateSubjectEndpoint' (string)
			// → Výsledek: registerClass() → lazy-load třídy pod daným názvem
			if (is_string($definition)) {
				if (!class_exists($definition)) {
					throw new \InvalidArgumentException(
						"Třída endpointu '{$definition}' pro '{$name}' nebyla nalezena. "
						. "Zkontrolujte autoloading, nebo použijte formát s klíčem 'file':\n"
						. "  '{$name}' => ['class' => \\{$definition}::class, 'file' => __DIR__ . '/path/to/{$definition}.php']"
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
	 * Registruje třídu s #[Endpoint] atributem (autoloaded).
	 *
	 * Název endpointu se automaticky načte z atributu.
	 * Třída musí dědit z AttributeEndpoint a mít #[Endpoint] atribut.
	 *
	 * @param class-string $className FQCN třídy
	 *
	 * @throws \InvalidArgumentException Pokud třída neexistuje nebo nemá správný atribut/dědičnost
	 */
	private function registerAttributeClass(string $className): void
	{
		if (!class_exists($className)) {
			throw new \InvalidArgumentException(
				"Třída endpointu '{$className}' nebyla nalezena. "
				. "Zkontrolujte autoloading, nebo použijte formát s klíčem 'file':\n"
				. "  ['class' => \\{$className}::class, 'file' => __DIR__ . '/path/to/file.php']"
			);
		}

		$this->validateAttributeEndpointClass($className);

		$endpointName = AttributeEndpoint::resolveEndpointName($className);
		$this->registerClass($endpointName, $className);
	}

	/**
	 * Registruje třídu s #[Endpoint] atributem z pole konfigurace.
	 *
	 * Podporuje klíče:
	 *   - 'class'     (required) FQCN třídy
	 *   - 'file'      (optional) Cesta k souboru pro require_once
	 *   - 'transform' (optional) Callable pro runtime override transformace
	 *
	 * @param array $definition Pole s klíči 'class', volitelně 'file' a 'transform'
	 *
	 * @throws \InvalidArgumentException Pokud třída/soubor neexistuje nebo nemá správný atribut
	 */
	private function registerAttributeClassFromArray(array $definition): void
	{
		$className = $definition['class'];
		$file = $definition['file'] ?? null;
		$transform = $definition['transform'] ?? null;

		if (!is_string($className)) {
			throw new \InvalidArgumentException(
				"Klíč 'class' pro endpoint musí být string (FQCN třídy)."
			);
		}

		// Načti soubor pokud je uvedeno
		if ($file !== null) {
			if (!is_string($file) || !is_file($file)) {
				throw new \InvalidArgumentException(
					"Soubor '{$file}' pro endpoint třídy '{$className}' nebyl nalezen. "
					. "Zkontrolujte cestu v klíči 'file' (tip: použijte __DIR__ pro relativní cestu)."
				);
			}
			require_once $file;
		}

		if (!class_exists($className)) {
			$hint = $file === null
				? " Přidejte klíč 'file' s cestou k souboru, nebo zkontrolujte autoloading."
				: " Soubor '{$file}' byl načten, ale třída v něm nebyla nalezena.";

			throw new \InvalidArgumentException(
				"Třída endpointu '{$className}' nebyla nalezena.{$hint}"
			);
		}

		$this->validateAttributeEndpointClass($className);

		$endpointName = AttributeEndpoint::resolveEndpointName($className);

		// Pokud je transform, uložíme ho pro pozdější injekci při lazy-load
		if ($transform !== null && is_callable($transform)) {
			$this->pendingTransforms[$endpointName] = $transform;
		}

		$this->registerClass($endpointName, $className);
	}

	/**
	 * Validuje, že třída dědí z AttributeEndpoint a má #[Endpoint] atribut.
	 *
	 * @param class-string $className FQCN třídy
	 * @throws \InvalidArgumentException Pokud validace selže
	 */
	private function validateAttributeEndpointClass(string $className): void
	{
		if (!is_subclass_of($className, AttributeEndpoint::class)) {
			throw new \InvalidArgumentException(
				"Třída '{$className}' musí dědit ze Zoom\\PlatformBridge\\AI\\API\\AttributeEndpoint "
				. "a mít atribut #[Endpoint(...)]."
			);
		}
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

            // Injektuj pending transform callable (z bridge-config registrace)
            if (isset($this->pendingTransforms[$endpointName]) && $endpoint instanceof AttributeEndpoint) {
                $endpoint->setTransform($this->pendingTransforms[$endpointName]);
                unset($this->pendingTransforms[$endpointName]);
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
