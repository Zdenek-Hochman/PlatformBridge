<?php

declare(strict_types=1);

namespace PlatformBridge;

use PlatformBridge\Paths\PathResolver;

use PlatformBridge\Config\DTO\{
	PathsDto,
	UrlsDto,
	SecurityDto,
	TranslationsDto
};

/**
 * Konfigurační objekt pro PlatformBridge.
 *
 * Immutable value object obsahující všechny konfigurační hodnoty pro běh PlatformBridge.
 *
 * Pro vytváření konfigurace používejte doporučeně {@see PlatformBridgeBuilder}.
 *
 * @see PlatformBridgeBuilder Pro doporučený způsob vytváření konfigurace
 */
final class PlatformBridgeConfig
{
    private ?string $secretKey;
    private ?int $resolvedTtl;

    public function __construct(
		private PathsDto $paths,
		private UrlsDto $urls,
		private SecurityDto $security,
		private TranslationsDto $translations,
    ) {
        [$this->secretKey, $this->resolvedTtl] = $this->loadSecurityConfig();
    }

    public function getPathResolver(): PathResolver
    {
        return $this->paths->resolver;
    }

    /**
     * Vrátí cestu ke složce s JSON konfigurací (blocks.json, layouts.json, generators.json).
     *
     * @return string Absolutní cesta ke složce s konfigurací
     * @internal
     */
    public function getConfigPath(): string
    {
        return $this->paths->getConfigPath();
    }

    /**
     * Vrátí cestu ke složce se šablonami (views).
     *
     * @return string Absolutní cesta k šablonám
     * @internal
     */
    public function getViewsPath(): string
    {
        if ($this->paths->viewsPath !== null) {
            return $this->paths->viewsPath;
        }

        return $this->paths->resolver->viewsPath();
    }

    /**
     * Vrátí cestu ke složce pro cache šablon.
     *
     * @return string Absolutní cesta ke cache
     * @internal
     */
    public function getCachePath(): string
    {
        if ($this->paths->cachePath !== null) {
            return $this->paths->cachePath;
        }

        return $this->paths->resolver->cachePath();
    }

    /**
     * Vrátí URL pro načítání assetů (JS/CSS).
     *
     * @return string URL k asset složce
     * @internal Používá AssetManager
     */
    public function getAssetUrl(): string
    {
        return $this->urls->assetUrl;
    }

    /**
     * Vrátí URL k API endpointu.
     *
     * @return string URL k API endpointu
     * @internal
     */
    public function getApiUrl(): string
    {
        return $this->urls->apiUrl;
    }

    /**
     * Vrátí secret key pro HMAC podepisování parametrů.
     * Načítá se ze security-config.php pokud je HMAC zapnutý.
     *
     * @return string|null Tajný klíč nebo null, pokud není nastaven nebo je HMAC vypnutý
     * @internal
     */
    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    /**
     * Vrátí TTL (time-to-live) pro podepsané parametry v sekundách.
     * Pokud není nastaveno, parametry nikdy neexpirují.
     *
     * @return int|null TTL v sekundách nebo null
     * @internal
     */
    public function getParamsTtl(): ?int
    {
        return $this->resolvedTtl;
    }

    /**
     * Načte bezpečnostní konfiguraci ze security-config.php a vrátí secretKey a TTL.
     *
     * Pokud je HMAC vypnutý, vrací [null, null].
     * Jinak načte konfigurační pole ze souboru, zkontroluje přítomnost 'secretKey',
     * a TTL vezme buď z parametru builderu, nebo z configu (pokud není nastaveno).
     *
     * @return array{0: string|null, 1: int|null} Secret key a TTL
     * @throws \InvalidArgumentException Pokud chybí 'secretKey' v configu
     */
    private function loadSecurityConfig(): array
    {
        if (!$this->security->useHmac) {
            return [null, null];
        }

        $config = $this->requireSecurityConfig();

        $secretKey = $config['secretKey'] ?? throw new \InvalidArgumentException("Security config must contain 'secretKey'.");

        $ttl = $this->security->paramsTtl ?? ($config['ttl'] ?? null);

        return [$secretKey, $ttl];
    }

    /**
     * Načte a vrátí pole z konfiguračního souboru security-config.php.
     *
     * Ověří existenci souboru, nastaví konstantu BRIDGE_BOOTSTRAPPED (pokud ještě není),
     * a vyžádá soubor. Pokud soubor neexistuje nebo nevrací pole, vyhodí výjimku.
     *
     * @return array<string, mixed> Konfigurační pole se security nastavením
     * @throws \InvalidArgumentException Pokud soubor neexistuje nebo nevrací pole
     */
    private function requireSecurityConfig(): array
    {
        clearstatcache(true, $this->security->configPath);

        if (!file_exists($this->security->configPath)) {
            throw new \InvalidArgumentException(
                "Security config not found: {$this->security->configPath}"
            );
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $this->security->configPath;

        if (!is_array($config)) {
            throw new \InvalidArgumentException('Security config must return an array.');
        }

        return $config;
    }

    /**
     * Vrátí aktuální nastavenou lokalizaci (jazyk).
     *
     * @return string Kód jazyka (např. 'cs', 'en')
     * @internal
     */
    public function getLocale(): string
    {
        return $this->translations->locale;
    }

    /**
     * Vrátí mysqli instanci pro překladový systém.
     *
     * @return \mysqli|null mysqli připojení nebo null pokud není nastaveno
     * @internal
     */
    public function getMysqli(): ?\mysqli
    {
        return $this->translations->mysqli;
    }

    /**
     * Vrátí název tabulky pro překlady.
     *
     * @return string Název tabulky (default: 'pb_translations')
     * @internal
     */
    public function getTranslationTable(): string
    {
        return $this->translations->table;
    }

    /**
     * Vrátí konfiguraci handlerů pro formulářová pole.
     *
     * @return array{handlers: class-string[], default: class-string} Pole tříd handlerů a výchozí handler
     *
     * @see \PlatformBridge\Handler\HandlerRegistry
     */
    public function getHandlersConfig(): array
    {
        return [
            'handlers' => [
                \PlatformBridge\Handler\Fields\RadioHandler::class,
                \PlatformBridge\Handler\Fields\SelectHandler::class,
                \PlatformBridge\Handler\Fields\CheckboxHandler::class,
                \PlatformBridge\Handler\Fields\TextareaHandler::class,
                \PlatformBridge\Handler\Fields\TickBoxHandler::class,
                \PlatformBridge\Handler\Fields\HiddenHandler::class,
                \PlatformBridge\Handler\Fields\TextHandler::class,
                \PlatformBridge\Handler\Fields\NumberHandler::class,
                \PlatformBridge\Handler\Fields\DateHandler::class,
                \PlatformBridge\Handler\Fields\FileHandler::class,
            ],
            'default' => \PlatformBridge\Handler\Fields\TextHandler::class,
        ];
    }
}
