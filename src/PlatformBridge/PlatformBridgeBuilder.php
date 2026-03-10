<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

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
    private bool $useHmac = false;
    private ?int $paramsTtl = null;

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
     * Pokud není nastaveno, automaticky se detekuje z DOCUMENT_ROOT:
     *   - Standalone: '/{basePath}/public/platformbridge' (build output v public/)
     *   - Vendor: '/platformbridge' (soubory publikované instalátorem)
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
            configPath: $this->resolveConfigPath(),
            viewsPath: $this->resolveViewsPath(),
            cachePath: $this->resolveCachePath(),
            // translationsPath: $this->resolveTranslationsPath(),
            locale: $this->locale,
            bridgeConfigPath: $this->resolveBridgeConfigPath(),
            assetUrl: $this->resolveAssetUrl(),
            useHmac: $this->useHmac,
            paramsTtl: $this->paramsTtl,
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
     * Vrátí výchozí cestu k resources v rámci balíčku (fallback pro všechny složky).
     *
     * @return string Absolutní cesta k resources
     */
    private function getPackageResourcesPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources';
    }

    /**
     * Vrátí výslednou cestu ke konfiguraci (s fallbackem na resources/config/defaults).
     *
     * @return string
     */
    private function resolveConfigPath(): string
    {
        return $this->configPath ?? $this->getPackageResourcesPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults';
    }

    /**
     * Vrátí výslednou cestu ke konfiguračnímu souboru bridge-config.php.
     *
     * @return string
     */
    private function resolveBridgeConfigPath(): string
    {
        return $this->bridgeConfigPath ?? $this->getPackageResourcesPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bridge-config.php';
    }

    /**
     * Vrátí výslednou cestu ke složce se šablonami (views).
     *
     * @return string
     */
    private function resolveViewsPath(): string
    {
        return $this->viewsPath ?? $this->getPackageResourcesPath() . DIRECTORY_SEPARATOR . 'views';
    }

    /**
     * Vrátí výslednou cestu ke složce s překlady (translations).
     *
     * @return string
     */
    private function resolveTranslationsPath(): string
    {
        return $this->translationsPath ?? $this->getPackageResourcesPath() . DIRECTORY_SEPARATOR . 'translations';
    }

    /**
     * Vrátí výslednou URL pro assety.
     *
     * Priorita:
     *   1. Explicitně nastavená URL přes withAssetUrl()
     *   2. Auto-detekce na základě vendor/standalone režimu
     *
     * @return string URL ke složce s assety
     */
    private function resolveAssetUrl(): string
    {
        if ($this->assetUrl !== null) {
            return $this->assetUrl;
        }

        return \Zoom\PlatformBridge\Installer\Installer::getDefaultAssetUrl(
            dirname(__DIR__, 2)
        );
    }

    /**
     * Vrátí výslednou cestu ke složce pro cache šablon.
     * Pokud složka neexistuje, automaticky ji vytvoří.
     *
     * @return string
     */
    private function resolveCachePath(): string
    {
        if ($this->cachePath) {
            return $this->cachePath;
        }

        $defaultCache = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($defaultCache)) {
            @mkdir($defaultCache, 0755, true);
        }

        return $defaultCache;
    }
}
