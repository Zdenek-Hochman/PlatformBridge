<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Asset\AssetManager;
use Zoom\PlatformBridge\Config\ConfigManager;
use Zoom\PlatformBridge\Config\ConfigLoader;
use Zoom\PlatformBridge\Config\ConfigValidator;
use Zoom\PlatformBridge\Handler\HandlerRegistry;
use Zoom\PlatformBridge\Handler\FieldFactory;
use Zoom\PlatformBridge\Template\Engine;
// use Zoom\PlatformBridge\Translator\Translator;
use Zoom\PlatformBridge\Runtime\FormRenderer;
use Zoom\PlatformBridge\Security\SignedParams;
use Zoom\PlatformBridge\Error\ErrorHandler;
use Zoom\PlatformBridge\Error\ErrorRenderer;

/**
 * Hlavní fasáda knihovny PlatformBridge.
 *
 * Tato třída je vstupním bodem pro externí aplikace.
 * Poskytuje fluent API pro konfiguraci a použití knihovny.
 *
 * @package Zoom\PlatformBridge
 */
final class PlatformBridge
{
    private ConfigManager $configManager;
    private HandlerRegistry $handlerRegistry;
    private FieldFactory $fieldFactory;
    private Engine $templateEngine;
    private ?AssetManager $assetManager = null;
    // private Translator $translator;
    private FormRenderer $formRenderer;
    private ?SignedParams $signedParams = null;

    private function __construct(private readonly PlatformBridgeConfig $config)
    {
        $this->boot();
    }

    /**
     * Vytvoří nový builder pro konfiguraci instance.
     *
     * @return PlatformBridgeBuilder Builder pro konfiguraci PlatformBridge
     */
    public static function create(): PlatformBridgeBuilder
    {
        (new ErrorHandler(new ErrorRenderer()))->register();

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
     *
     * @internal
     */
    public static function fromConfig(PlatformBridgeConfig $config): self
    {
        return new self($config);
    }

    /**
     * Inicializuje všechny hlavní komponenty knihovny PlatformBridge.
     *
     * Orchestrátor, který deleguje na specializované boot metody.
     * Pořadí volání je důležité – pozdější kroky závisí na předchozích.
     */
    private function boot(): void
    {
        $this->bootErrorHandler();
        $this->bootConfig();
        $this->bootTemplateEngine();
        $this->bootHandlers();
        $this->bootFormRenderer();
        $this->bootAssetManager();
        $this->bootSecurity();
    }

    /**
     * 1. Registrace globálního error handleru pro lepší zachytávání chyb v rámci knihovny.
     */
    private function bootErrorHandler(): void
    {
        (new ErrorHandler(new ErrorRenderer()))->register();
    }

    /**
     * 2. Načtení konfigurace (bloky, layouty, generátory) s validací.
     *
     * Cesta ke JSON konfiguračním souborům se řeší přes PlatformBridgeConfig:
     *   - Explicitní withConfigPath() má přednost
     *   - Fallback: PathResolver::resolvedConfigPath()
     * Package defaults slouží jako fallback pro ConfigLoader.
     */
    private function bootConfig(): void
    {
        $paths = $this->config->getPathResolver();

        $loader = new ConfigLoader(
            $this->config->getConfigPath(),
            $paths->packageDefaultsPath(),
            new ConfigValidator()
        );

        $this->configManager = new ConfigManager($loader);
        $this->configManager->load();
    }

    /**
     * 3. Inicializace šablonovacího enginu s cestami k šablonám a cache.
     */
    private function bootTemplateEngine(): void
    {
        $this->templateEngine = new Engine([
            'tpl_dir'   => $this->config->getViewsPath(),
            'cache_dir' => $this->config->getCachePath(),
            'debug'     => false,
        ]);
    }

    /**
     * 4. Registrace handlerů a vytvoření továrny na pole (FieldFactory).
     */
    private function bootHandlers(): void
    {
        $this->handlerRegistry = $this->createHandlerRegistry();
        $this->fieldFactory = new FieldFactory($this->handlerRegistry);
    }

    /**
     * 5. Inicializace rendereru formulářů s továrnou, konfigurací a šablonovacím enginem.
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
     * 6. Inicializace asset manageru pro správu a generování HTML assetů (CSS/JS).
     * URL se detekuje automaticky podle režimu (standalone vs vendor).
     */
    private function bootAssetManager(): void
    {
        $this->assetManager = new AssetManager($this->config->getAssetUrl());
    }

    /**
     * 7. Inicializace podepisování parametrů, pokud je nastaven secret key.
     */
    private function bootSecurity(): void
    {
        $secretKey = $this->config->getSecretKey();

        if ($secretKey !== null) {
            $this->signedParams = new SignedParams($secretKey, $this->config->getParamsTtl());
        }
    }

    /**
     * Vytvoří a nakonfiguruje registr handlerů.
     *
     * @return HandlerRegistry Nově vytvořený a nakonfigurovaný registr handlerů
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

    /**
     * Vykreslí formulář podle ID generátoru.
     *
     * @param string $generatorId ID generátoru z konfigurace
     * @param array<string, mixed> $context Dynamické hodnoty pro injekci do bloků formuláře
     *
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
     * @param array<string, array<string, mixed>> $additionalParams Další parametry pro šablonu (např. 'inject' pro dynamické hodnoty)

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
     *
     * @return array Sestavené parametry pro šablonu
     */
    private function buildParams(string $generatorId, array $extra): array
    {
        $base = [
            'endpoint'      => $this->configManager->getConfigValue($generatorId, 'endpoint'),
            'request_amount' => $this->configManager->getConfigValue($generatorId, 'api.request_amount'),
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
        return $this->assetManager ? $this->assetManager->getAssets() . $html : $html;
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
