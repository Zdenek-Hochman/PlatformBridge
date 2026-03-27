<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Paths\PathResolver;
use Zoom\PlatformBridge\Paths\PathResolverFactory;
use Zoom\PlatformBridge\Paths\UrlResolver;

/**
 * Builder pro konfiguraci PlatformBridge instance.
 *
 * Umožňuje fluent konfiguraci všech cest a nastavení před sestavením immutable objektu PlatformBridgeConfig.
 *
 * @example
 * ```php
 * $bridge = PlatformBridgeBuilder::create()
 *     ->withConfigPath('/path/to/config')
 *     ->withViewsPath('/path/to/views')
 *     ->withCachePath('/path/to/cache')
 *     ->withSecretKey(true)
 *     ->withParamsTtl(3600)
 *     ->build();
 * ```
 *
 * @see PlatformBridgeConfig
 */
final class PlatformBridgeBuilder
{
    private ?string $configPath = null;
    private ?string $viewsPath = null;
    private ?string $cachePath = null;
    private string $locale = 'cs';
    private bool $useHmac = false;
    private ?int $paramsTtl = null;

    // PathResolver se vytvoří jednou a sdílí
    private PathResolver $paths;
    private UrlResolver $urlResolver;

    public function __construct()
    {
        $this->paths ??= PathResolverFactory::auto(dirname(__DIR__, 2));
		$this->urlResolver = new UrlResolver($this->paths);
    }

    /**
     * Nastaví cestu ke složce s JSON konfigurací
     * (blocks.json, layouts.json, generators.json).
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withConfigPath(string $path): self
    {
        $this->configPath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví cestu ke složce se šablonami (views).
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withViewsPath(string $path): self
    {
        $this->viewsPath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví jazyk aplikace (locale).
     *
     * @param string $locale Kód jazyka (např. 'cs', 'en')
     * @return self
     */
    public function withLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Zapne/vypne HMAC podepisování parametrů (ochrana integrity dat).
     * Secret key se načte ze security-config.php.
     *
     * @param bool $enable Zapnout/vypnout HMAC (default: true)
     * @return self
     */
    public function withSecretKey(bool $enable = true): self
    {
        $this->useHmac = $enable;
        return $this;
    }

    /**
     * Nastaví TTL (time-to-live) pro podepsané parametry.
     * Pokud není nastaveno, parametry nikdy nevyprší.
     *
     * @param int $seconds Platnost v sekundách
     * @return self
     */
    public function withParamsTtl(int $seconds): self
    {
        $this->paramsTtl = $seconds;
        return $this;
    }

    /**
     * Sestaví a vrátí nakonfigurovanou instanci PlatformBridge.
     *
     * @return PlatformBridge
     * @throws \InvalidArgumentException Pokud chybí povinná konfigurace nebo jsou cesty neplatné
     *
     * Bezpečnost:
     *  - Pokud je HMAC zapnutý, musí být nastaven validní secret key v security-config.php.
     *  - TTL pomáhá chránit proti replay útokům, doporučuje se nastavit.
     */
    public function build(): PlatformBridge
    {
        $config = new PlatformBridgeConfig(
            configPath:         $this->configPath,
            viewsPath:          $this->viewsPath,
            cachePath:          $this->cachePath,

            assetUrl:           $this->urlResolver->assetUrl(),
            apiUrl:             $this->urlResolver->apiUrl(),

            securityConfigPath: $this->paths->securityConfigFile(),

            locale:             $this->locale,
            useHmac:            $this->useHmac,
            paramsTtl:          $this->paramsTtl,

            pathResolver:       $this->paths,
        );

        return PlatformBridge::fromConfig($config);
    }

    /**
     * Normalizuje cestu - odstraní trailing slash a lomítka.
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR . '/\\');
    }
}
