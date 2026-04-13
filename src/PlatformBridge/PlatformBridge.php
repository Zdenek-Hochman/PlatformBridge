<?php

declare(strict_types=1);

namespace PlatformBridge;

use PlatformBridge\Asset\AssetManager;
use PlatformBridge\Template\Engine;
use PlatformBridge\Config\ConfigManager;

use PlatformBridge\Error\{
	ErrorHandler,
	ErrorRenderer
};

use PlatformBridge\Translator\{
	Translator,
	TranslationEndpoint,
	VariableResolver
};

use PlatformBridge\Runtime\FormRenderer;
use PlatformBridge\Security\SignedParams;

use PlatformBridge\Container\{
	Container,
	ServiceProvider
};

/**
 * Hlavní fasáda knihovny PlatformBridge.
 *
 * Tato třída je vstupním bodem pro externí aplikace a poskytuje fluent API pro konfiguraci a použití knihovny.
 *
 * @package PlatformBridge
 */
final class PlatformBridge
{
    private Container $container;

    private function __construct(private readonly PlatformBridgeConfig $config)
    {
        $this->boot();
    }

    /**
     * Vytvoří nový builder pro konfiguraci instance PlatformBridge.
     *
     * @return PlatformBridgeBuilder Builder pro konfiguraci PlatformBridge
     */
    public static function create(): PlatformBridgeBuilder
    {
        self::bootErrorHandler();
        return new PlatformBridgeBuilder();
    }

    /**
     * Vytvoří instanci s výchozí konfigurací (pro rychlé použití).
     *
     * @return self Nová instance PlatformBridge s výchozí konfigurací
     */
    public static function createDefault(): self
    {
        return self::create()->build();
    }

    /**
     * Interní metoda pro vytvoření instance z builderu.
     *
     * @param PlatformBridgeConfig $config Konfigurace pro PlatformBridge
     * @return self Nová instance PlatformBridge
     * @internal
     */
    public static function fromConfig(PlatformBridgeConfig $config): self
    {
        return new self($config);
    }

    /**
     * Inicializuje DI kontejner a registruje všechny služby.
     */
    private function boot(): void
    {
        self::bootErrorHandler();

        $this->container = new Container();
        (new ServiceProvider($this->config))->register($this->container);
    }

    /**
     * 1. Registrace globálního error handleru pro lepší zachytávání chyb v rámci knihovny.
     *
     * @return void
     */
    private static function bootErrorHandler(): void
    {
        (new ErrorHandler(new ErrorRenderer()))->register();
    }

    /**
     * Vykreslí formulář podle ID generátoru.
     *
     * @param string $generatorId ID generátoru z konfigurace
     * @param array<string, mixed> $context Dynamické hodnoty pro injekci do bloků formuláře
     * @return array<int, array<string, mixed>> Pole sekcí s HTML obsahem
     */
    private function renderForm(string $generatorId, array $context = []): array
    {
        return $this->container->get(FormRenderer::class)->build($generatorId, $context);
    }

    /**
     * Vrátí kompletní HTML wrapper s formulářem.
     *
     * @param string $generatorId ID generátoru
     * @param array<string, mixed> $additionalParams Další parametry pro šablonu (např. 'inject' pro dynamické hodnoty)
     * @return string Kompletní HTML výstup
     *
     * @example
     *   $html = $bridge->renderFullForm('my-generator', ['inject' => ['foo' => 'bar']]);
     */
    public function renderFullForm(string $generatorId, array $additionalParams = []): string
    {
        // Extrakce dynamických hodnot pro formulářové bloky
        $inject = $additionalParams['inject'] ?? [];
        unset($additionalParams['inject']);

        $sections = $this->renderForm($generatorId, $inject);
        $generator = $this->container->get(ConfigManager::class)->getGenerator($generatorId);

        // Sestavení kompletních parametrů
        $params = $this->buildParams($generatorId, $additionalParams);

        $template = [
            'title'        => $generator['label'] ?? $generatorId,
            'data'         => $sections,
            'signedParams' => $this->buildSignedParams($params),
            'params'       => $this->buildRawParams($params),
            //TODO: Přidat hash téhle adresy
            'apiUrl'       => $this->config->getApiUrl(),
        ];

        $html = $this->container->get(Engine::class)->assign($template)->render('/Atoms/Wrapper');

        return $this->prependAssets($html);
    }

    /**
     * Sestaví pole parametrů pro šablonu.
     *
     * @param string $generatorId ID generátoru
     * @param array $extra Dodatečné parametry
     * @return array Sestavené parametry pro šablonu
     */
    private function buildParams(string $generatorId, array $extra): array
    {
        $configManager = $this->container->get(ConfigManager::class);

        $base = [
            'endpoint'       => $configManager->getConfigValue($generatorId, 'endpoint'),
            'request_amount' => $configManager->getConfigValue($generatorId, 'api.request_amount'),
            'config_path'    => $this->config->getConfigPath(),
            'locale'         => $this->config->getLocale(),
        ];

        return [
            'config' => $base,
            ...$extra,
        ];
    }

    /**
     * Vrátí podepsané parametry, pokud je nastaveno podepisování.
     *
     * @param array $params Parametry k podepsání
     * @return string|null Podepsané parametry jako string, nebo null pokud není podepisování aktivní
     */
    private function buildSignedParams(array $params): ?string
    {
        if (!$this->container->has(SignedParams::class)) {
            return null;
        }

        return $this->container->get(SignedParams::class)->sign($params);
    }

    /**
     * Vrátí parametry v surové podobě, pokud není aktivní podepisování.
     *
     * @param array $params Parametry
     * @return array|null Parametry jako pole, nebo null pokud je aktivní podepisování
     */
    private function buildRawParams(array $params): ?array
    {
        return $this->container->has(SignedParams::class) ? null : $params;
    }

    /**
     * Přidá HTML assetů (CSS/JS) před výstupní HTML, pokud je asset manager aktivní.
     *
     * @param string $html Výstupní HTML
     * @return string HTML s případně přidanými assety
     */
    private function prependAssets(string $html): string
    {
        return ($this->container->get(AssetManager::class)->getAssets() ?? '') . $html;
    }

    /**
     * Vrátí instanci šablonovacího enginu pro vlastní renderování.
     *
     * @return Engine Instance šablonovacího enginu
     */
    public function getTemplateEngine(): Engine
    {
        return $this->container->get(Engine::class);
    }

    /**
     * Vrátí instanci Translatoru.
     *
     * @return Translator Instance překladače
     */
    public function getTranslator(): Translator
    {
        return $this->container->get(Translator::class);
    }

    /**
     * Vrátí VariableResolver pro nahrazování {$domain.key} proměnných.
     *
     * @return VariableResolver
     */
    public function getVariableResolver(): VariableResolver
    {
        return $this->container->get(VariableResolver::class);
    }

    /**
     * Vytvoří TranslationEndpoint pro servírování překladů frontendu.
     *
     * @return TranslationEndpoint HTTP handler pro frontend překlady
     */
    // public function createTranslationEndpoint(): TranslationEndpoint
    // {
    //     return new TranslationEndpoint($this->translator);
    // }

    /**
     * Přeloží klíče v poli odpovědi z API před vykreslením do šablony.
     *
     * Typické použití: AI API vrátí pole s anglickými klíči,
     * tato metoda je přeloží do aktuálního locale.
     *
     * @param array $response Odpověď z API
     * @param string $domain Doména překladů (default: 'api')
     * @return array Odpověď s přeloženými klíči
     *
     * @example
     *   $result = $bridge->translateResponseKeys($apiResponse, 'api');
     *   $engine->assign(['response' => $result])->render('/Components/NestedResult');
     */
    // public function translateResponseKeys(array $response, string $domain = 'api'): array
    // {
    //     return $this->translator->translateKeys($response, $domain);
    // }

    /**
     * Vrátí aktuální konfiguraci PlatformBridge.
     *
     * @return PlatformBridgeConfig Aktuální konfigurace
     */
    public function getConfig(): PlatformBridgeConfig
    {
        return $this->config;
    }

    /**
     * Vrátí všechny assety (CSS + JS).
     * Každý asset se vrátí pouze jednou.
     *
     * @return string HTML s <link> a <script> tagy
     */
    public function getAssets(): string
    {
        return $this->container->get(AssetManager::class)->getAssets();
    }
}
