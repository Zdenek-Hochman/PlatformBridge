<?php

declare(strict_types=1);

namespace PlatformBridge\Container;

use PlatformBridge\PlatformBridgeConfig;
use PlatformBridge\Asset\AssetManager;
use PlatformBridge\Template\Engine;

use PlatformBridge\Config\{
	ConfigManager,
	ConfigLoader,
	ConfigValidator,
};

use PlatformBridge\Handler\{
	HandlerRegistry,
	FieldFactory,
	HandlerConfig
};

use PlatformBridge\Translator\{
	Translator,
	VariableResolver
};

use PlatformBridge\Translator\Adapter\MysqliAdapter;
use PlatformBridge\Translator\Loader\PlatformLoader;
use PlatformBridge\Runtime\FormRenderer;
use PlatformBridge\Security\SignedParams;

/**
 * Registruje všechny služby PlatformBridge do DI kontejneru.
 *
 * Každá služba je lazy-loaded – vytvoří se až při prvním požadavku.
 * Závislosti mezi službami jsou řešeny přes kontejner.
 *
 * @package PlatformBridge\Container
 */
final class ServiceProvider
{
    public function __construct(
        private readonly PlatformBridgeConfig $config,
    ) {}

    /**
     * Zaregistruje všechny tovární funkce do kontejneru.
     */
    public function register(Container $container): void
    {
        $this->registerTranslator($container);
        $this->registerConfig($container);
        $this->registerTemplateEngine($container);
        $this->registerHandlers($container);
        $this->registerFormRenderer($container);
        $this->registerAssetManager($container);
        $this->registerSecurity($container);
    }

    /**
     * Registruje překladový systém.
     *
     * Translator se startuje před konfigurací, aby VariableResolver
     * mohl nahrazovat {$domain.key} proměnné v JSON konfiguracích.
     */
    private function registerTranslator(Container $container): void
    {
        $container->set(Translator::class, function () {
            $paths = $this->config->getPathResolver();
            $locale = $this->config->getLocale();

            return Translator::create(
                locale: $locale,
                langPath: $paths->langPath(),
                platformLoader: $this->createPlatformLoader($locale),
            );
        });

        $container->set(VariableResolver::class, function (Container $c) {
            return $c->get(Translator::class)->getVariableResolver();
        });
    }

    /**
     * Registruje konfigurační systém (ConfigLoader + ConfigManager).
     */
    private function registerConfig(Container $container): void
    {
        $container->set(ConfigLoader::class, function (Container $c) {
            return new ConfigLoader(
                $this->config->getConfigPath(),
                $this->config->getPathResolver(),
                new ConfigValidator(),
                $c->get(VariableResolver::class),
            );
        });

        $container->set(ConfigManager::class, function (Container $c) {
            $manager = new ConfigManager($c->get(ConfigLoader::class));
            $manager->load();

            return $manager;
        });
    }

    /**
     * Registruje šablonovací engine.
     */
    private function registerTemplateEngine(Container $container): void
    {
        $container->set(Engine::class, function () {
            return new Engine([
                'tpl_dir'   => $this->config->getViewsPath(),
                'cache_dir' => $this->config->getCachePath(),
                'debug'     => false,
            ]);
        });
    }

    /**
     * Registruje handlery a field factory.
     */
    private function registerHandlers(Container $container): void
    {
        $container->set(HandlerRegistry::class, function () {
            $registry = new HandlerRegistry();

            foreach (HandlerConfig::HANDLERS as $handlerClass) {
                $registry->register($handlerClass);
            }

            $registry->setDefaultHandler(HandlerConfig::DEFAULT);

            return $registry;
        });

        $container->set(FieldFactory::class, function (Container $c) {
            return new FieldFactory($c->get(HandlerRegistry::class));
        });
    }

    /**
     * Registruje renderer formulářů.
     */
    private function registerFormRenderer(Container $container): void
    {
        $container->set(FormRenderer::class, function (Container $c) {
            return new FormRenderer(
                $c->get(FieldFactory::class),
                $c->get(ConfigManager::class),
                $c->get(Engine::class),
            );
        });
    }

    /**
     * Registruje správce statických assetů.
     */
    private function registerAssetManager(Container $container): void
    {
        $container->set(AssetManager::class, function () {
            return new AssetManager($this->config->getAssetUrl());
        });
    }

    /**
     * Registruje bezpečnostní komponenty (SignedParams).
     */
    private function registerSecurity(Container $container): void
    {
        $secretKey = $this->config->getSecretKey();

        if ($secretKey !== null) {
            $container->set(SignedParams::class, function () use ($secretKey) {
                return new SignedParams($secretKey, $this->config->getParamsTtl());
            });
        }
    }

    /**
     * Vytvoří PlatformLoader pro načítání překladů z databáze.
     */
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
}
