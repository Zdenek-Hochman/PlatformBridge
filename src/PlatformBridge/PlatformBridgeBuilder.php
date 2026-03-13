<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Config\PathResolver;

/**
 * Builder pro konfiguraci PlatformBridge instance.
 *
 * Umožňuje fluent konfiguraci všech cest a nastavení před sestavením immutable objektu PlatformBridgeConfig.
 *
 * Typické použití:
 *
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
    private ?string $translationsPath = null;
    private string $locale = 'cs';
    private ?string $bridgeConfigPath = null;
    private ?string $assetUrl = null;
    private ?string $apiUrl = null;
    private bool $useHmac = false;
    private ?int $paramsTtl = null;

    // PathResolver se vytvoří jednou a sdílí
    private PathResolver $paths;

    public function __construct()
    {
        $this->paths = new PathResolver(dirname(__DIR__, 2));
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
     * Nastaví cestu ke složce pro cache šablon.
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withCachePath(string $path): self
    {
        $this->cachePath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví cestu ke složce s překlady (translations).
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withTranslationsPath(string $path): self
    {
        $this->translationsPath = $this->normalizePath($path);
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
     * Nastaví cestu ke konfiguračnímu souboru bridge-config.php.
     * Tento soubor obsahuje secretKey a další bezpečnostní nastavení.
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withBridgeConfigPath(string $path): self
    {
        $this->bridgeConfigPath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví URL pro načítání assetů (JS/CSS).
     *
     * Pokud není nastaveno, automaticky se detekuje:
     *   - Standalone (dev): '/dist' (build output)
     *   - Vendor (prod): '/platformbridge' nebo '/public/platformbridge'
     *
     * @param string $url URL ke složce s js/ a css/ podsložkami
     * @return self
     */
    public function withAssetUrl(string $url): self
    {
        $this->assetUrl = $url;
        return $this;
    }

    /**
     * Nastaví URL k API endpointu.
     *
     * Pokud není nastaveno, automaticky se detekuje:
     *   - Standalone (dev): '/resources/stubs/api.php'
     *   - Vendor (prod): '/public/platformbridge/api.php'
     *
     * @param string $url URL k API endpointu
     * @return self
     */
    public function withApiUrl(string $url): self
    {
        $this->apiUrl = $url;
        return $this;
    }

    /**
     * Zapne/vypne HMAC podepisování parametrů (ochrana integrity dat).
     * Secret key se načte z bridge-config.php.
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
     *  - Pokud je HMAC zapnutý, musí být nastaven validní secret key v bridge-config.php.
     *  - TTL pomáhá chránit proti replay útokům, doporučuje se nastavit.
     */
    public function build(): PlatformBridge
    {
        $config = new PlatformBridgeConfig(
            configPath:       $this->configPath       ?? $this->paths->resolvedConfigPath(),
            viewsPath:        $this->viewsPath        ?? $this->paths->packageViewsPath(),
            cachePath:        $this->cachePath         ?? $this->paths->cachePath(),
            locale:           $this->locale,
            bridgeConfigPath: $this->bridgeConfigPath  ?? $this->paths->resolvedBridgeConfigFile(),
            assetUrl:         $this->assetUrl          ?? $this->resolveAssetUrl(),
            apiUrl:           $this->apiUrl            ?? $this->resolveApiUrl(),
            useHmac:          $this->useHmac,
            paramsTtl:        $this->paramsTtl,
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

    /**
     * Vrátí PathResolver pro přístup k cestám.
     */
    public function getPathResolver(): PathResolver
    {
        return $this->paths;
    }

    /**
     * Vrátí výslednou URL pro assety.
     *
     * Priorita:
     *   1. Explicitně nastavená URL přes withAssetUrl()
     *   2. Auto-detekce:
     *      - Standalone (dev): '/dist'
     *      - Vendor (prod): '/platformbridge' nebo '/public/platformbridge'
     *
     * @return string URL ke složce s assety
     */
    private function resolveAssetUrl(): string
    {
        return \Zoom\PlatformBridge\Installer\Installer::getDefaultAssetUrl(
            $this->paths->packageRoot()
        );
    }

    /**
     * Vrátí výslednou URL pro API endpoint.
     *
     * Priorita:
     *   1. Explicitně nastavená URL přes withApiUrl()
     *   2. Auto-detekce:
     *      - Standalone (dev): '/resources/stubs/api.php'
     *      - Vendor (prod): '/public/platformbridge/api.php'
     *
     * @return string URL k API endpointu
     */
    private function resolveApiUrl(): string
    {
        return \Zoom\PlatformBridge\Installer\Installer::getDefaultApiUrl(
            $this->paths->packageRoot()
        );
    }
}
