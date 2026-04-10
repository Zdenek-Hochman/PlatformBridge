<?php

declare(strict_types=1);

namespace PlatformBridge;

use PlatformBridge\Asset\AssetManager;
use PlatformBridge\Template\Engine;

use PlatformBridge\Config\{
	ConfigManager,
	ConfigLoader,
	ConfigValidator,
};

use PlatformBridge\Handler\{
	HandlerRegistry,
	FieldFactory
};

use PlatformBridge\Error\{
	ErrorHandler,
	ErrorRenderer
};

use PlatformBridge\Translator\{
	Translator,
	TranslationEndpoint,
	VariableResolver
};

use PlatformBridge\Translator\Adapter\MysqliAdapter;
use PlatformBridge\Translator\Loader\PlatformLoader;
use PlatformBridge\Runtime\FormRenderer;
use PlatformBridge\Security\SignedParams;

/**
 * Hlavní fasáda knihovny PlatformBridge.
 *
 * Tato třída je vstupním bodem pro externí aplikace a poskytuje fluent API pro konfiguraci a použití knihovny.
 *
 * @package PlatformBridge
 */
final class PlatformBridge
{
    private ConfigManager $configManager;
    private HandlerRegistry $handlerRegistry;
    private FieldFactory $fieldFactory;
    private Engine $templateEngine;
    private ?AssetManager $assetManager = null;
    private Translator $translator;
    private VariableResolver $variableResolver;
    private FormRenderer $formRenderer;
    private ?SignedParams $signedParams = null;

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
     * Inicializuje všechny hlavní komponenty knihovny PlatformBridge.
     */
    private function boot(): void
    {
        //TODO: Zvážit lazy loading komponent a DI pro lepší výkon a testovatelnost
        self::bootErrorHandler();
        $this->bootTranslator();
        $this->bootConfig();
        $this->bootTemplateEngine();
        $this->bootHandlers();
        $this->bootFormRenderer();
        $this->bootAssetManager();
        $this->bootSecurity();
    }

    /**
     * Inicializuje překladový systém.
     *
     * Translator se startuje před konfigurací, aby VariableResolver
     * mohl nahrazovat {\$domain.key} proměnné v JSON konfiguracích.
     */
    private function bootTranslator(): void
    {
        $paths = $this->config->getPathResolver();
        $locale = $this->config->getLocale();

        $this->translator = Translator::create(
            locale: $locale,
            langPath: $paths->langPath(),
            platformLoader: $this->createPlatformLoader($locale),
        );

        $this->variableResolver = $this->translator->getVariableResolver();
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
     * Inicializuje konfigurační systém aplikace.
     *
     * Vytvoří instanci {@see ConfigLoader}, která načítá konfiguraci
     * z hlavní konfigurační cesty a defaultních balíčkových cest.
     * Následně inicializuje {@see ConfigManager} a spustí načtení konfigurace.
     *
     * @return void
     */
    private function bootConfig(): void
    {
        $loader = new ConfigLoader(
            $this->config->getConfigPath(),
            $this->config->getPathResolver(),
            new ConfigValidator(),
            $this->variableResolver
        );

        $this->configManager = new ConfigManager($loader);
        $this->configManager->load();
    }

    /**
     * Inicializuje šablonovací engine aplikace.
     *
     * Vytvoří instanci {@see Engine} s nastavením cest k šablonám a cache.
     * Debug režim je zde explicitně vypnutý.
     *
     * @return void
     */
    private function bootTemplateEngine(): void
    {
        $this->templateEngine = new Engine([
            'tpl_dir'   => $this->config->getViewsPath(),
            'cache_dir' => $this->config->getCachePath(),
            'debug'     => false,
        ]);

        // Připoj translator do Engine pro runtime překlady v TPL ({_tran} tag)
        // $this->templateEngine->setTranslator($this->translator);
    }

    /**
     * Inicializuje handlery a továrnu pro vytváření polí.
     *
     * Vytvoří registry handlerů pomocí interní factory metody
     * a následně inicializuje {@see FieldFactory}, která tyto handlery využívá.
     *
     * @return void
     */
    private function bootHandlers(): void
    {
        // TODO: pokud handlery začnou potřebovat dependency → zavést factory auto-wiring
        $this->handlerRegistry = $this->createHandlerRegistry();
        $this->fieldFactory = new FieldFactory($this->handlerRegistry);
    }

    /**
     * Inicializuje renderer formulářů.
     *
     * Vytvoří instanci {@see FormRenderer}, která zajišťuje vykreslování formulářů
     * na základě field factory, konfigurace a šablonovacího enginu.
     *
     * @return void
     */
    private function bootFormRenderer(): void
    {
        $this->formRenderer = new FormRenderer(
            $this->fieldFactory,
            $this->configManager,
            $this->templateEngine
        );
    }

    /**
     * Inicializuje správce statických assetů.
     *
     * Vytvoří instanci {@see AssetManager} s base URL pro assety,
     * která se používá pro generování cest k CSS, JS a dalším zdrojům.
     *
     * @return void
     */
    private function bootAssetManager(): void
    {
        $this->assetManager = new AssetManager($this->config->getAssetUrl());
    }

    /**
     * Inicializuje bezpečnostní komponenty aplikace.
     *
     * Pokud je v konfiguraci dostupný secret key, vytvoří instanci {@see SignedParams}
     * pro podepisování a validaci parametrů s definovanou dobou platnosti (TTL).
     * V opačném případě zůstává funkcionalita nepodepsaných parametrů neaktivní.
     *
     * @return void
 */
    private function bootSecurity(): void
    {
        $secretKey = $this->config->getSecretKey();

        if ($secretKey !== null) {
            $this->signedParams = new SignedParams($secretKey, $this->config->getParamsTtl());
        }
    }

    /**
     * Vytvoří a nakonfiguruje registry handlerů.
     *
     * Na základě konfigurace instanciuje jednotlivé handlery a registruje je
     * do {@see HandlerRegistry}. Volitelně nastaví výchozí handler, pokud je definován.
     *
     * @return HandlerRegistry Inicializovaná registry handlerů
     */
    private function createHandlerRegistry(): HandlerRegistry
    {
        $handlersConfig = $this->config->getHandlersConfig();
        $registry = new HandlerRegistry();

        foreach ($handlersConfig['handlers'] as $handlerClass) {
            $registry->addHandler(new $handlerClass());
        }

        if (isset($handlersConfig['default'])) {
            $registry->setDefaultHandler($handlersConfig['default']);
        }

        // $registry->mapVariant('color-picker', CustomColorPickerHandler::class);

        return $registry;
    }

    private function createPlatformLoader(string $locale): ?PlatformLoader
    {
        $mysqli = $this->config->getMysqli();

        if ($mysqli === null) {
            return null;
        }

        $adapter = MysqliAdapter::fromMysqli(
            $mysqli,
            $this->config->getTranslationTable(),
            ensureTable: true,
        );

        return new PlatformLoader($adapter, $locale);
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
        return $this->formRenderer->build($generatorId, $context);
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
        $generator = $this->configManager->getGenerator($generatorId);

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

        $html = $this->templateEngine->assign($template)->render('/Atoms/Wrapper');

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
        $base = [
            'endpoint'       => $this->configManager->getConfigValue($generatorId, 'endpoint'),
            'request_amount' => $this->configManager->getConfigValue($generatorId, 'api.request_amount'),
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
        return $this->signedParams?->sign($params);
    }

    /**
     * Vrátí parametry v surové podobě, pokud není aktivní podepisování.
     *
     * @param array $params Parametry
     * @return array|null Parametry jako pole, nebo null pokud je aktivní podepisování
     */
    private function buildRawParams(array $params): ?array
    {
        return $this->signedParams ? null : $params;
    }

    /**
     * Přidá HTML assetů (CSS/JS) před výstupní HTML, pokud je asset manager aktivní.
     *
     * @param string $html Výstupní HTML
     * @return string HTML s případně přidanými assety
     */
    private function prependAssets(string $html): string
    {
        return ($this->assetManager?->getAssets() ?? '') . $html;
    }

    /**
     * Vrátí instanci šablonovacího enginu pro vlastní renderování.
     *
     * @return Engine Instance šablonovacího enginu
     */
    public function getTemplateEngine(): Engine
    {
        return $this->templateEngine;
    }

    /**
     * Vrátí instanci Translatoru.
     *
     * @return Translator Instance překladače
     */
    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    /**
     * Vrátí VariableResolver pro nahrazování {$domain.key} proměnných.
     *
     * @return VariableResolver
     */
    public function getVariableResolver(): VariableResolver
    {
        return $this->variableResolver;
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
        if ($this->assetManager === null) {
            return '';
        }
        return $this->assetManager->getAssets();
    }
}
