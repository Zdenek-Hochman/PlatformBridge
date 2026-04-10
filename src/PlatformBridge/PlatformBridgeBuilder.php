<?php

declare(strict_types=1);

namespace PlatformBridge;

use PlatformBridge\Paths\{
	PathResolver,
	PathResolverFactory,
	UrlResolver
};

use PlatformBridge\Config\DTO\{
	PathsDto,
	UrlsDto,
	SecurityDto,
	TranslationsDto
};

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
    private ?\mysqli $mysqli = null;
    private string $translationTable = 'pb_translations';

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
     * Předá mysqli instanci pro překladový systém.
     * Tabulka 'pb_translations' bude automaticky vytvořena pokud neexistuje.
     *
     * @param \mysqli $mysqli Existující mysqli připojení
     * @param string $tableName Název tabulky (default: 'pb_translations')
     * @return self
     */
    public function withMysqli(\mysqli $mysqli, string $tableName = 'pb_translations'): self
    {
        $this->mysqli = $mysqli;
        $this->translationTable = $tableName;
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
           	paths: new PathsDto(
				$this->configPath,
				$this->viewsPath,
				$this->cachePath,
				$this->paths
			),
			urls: new UrlsDto(
				$this->urlResolver->assetUrl(),
				$this->urlResolver->apiUrl()
			),
            security: new SecurityDto(
				$this->paths->securityConfigFile(),
				$this->useHmac,
				$this->paramsTtl
			),
			translations: new TranslationsDto(
				$this->mysqli,
				$this->translationTable,
				$this->locale
			),
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
