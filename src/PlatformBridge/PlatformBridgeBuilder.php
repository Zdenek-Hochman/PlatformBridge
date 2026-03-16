<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Config\PathResolver;

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
 * Cesta k security-config.php se nastavuje automaticky přes PathResolver
 * a nelze ji uživatelsky přepsat.
 *
 * @see PlatformBridgeConfig
 */
final class PlatformBridgeBuilder
{
    private ?string $configPath = null;
    private ?string $viewsPath = null;
    private string $locale = 'cs';
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
            configPath:         $this->configPath ?? $this->paths->resolvedConfigPath(),
            viewsPath:          $this->viewsPath ?? $this->paths->packageViewsPath(),
            cachePath:          $this->paths->cachePath(),
            assetUrl:           $this->resolveAssetUrl(),
            apiUrl:             $this->resolveApiUrl(),
            securityConfigPath: $this->paths->resolvedSecurityConfigFile(),
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
