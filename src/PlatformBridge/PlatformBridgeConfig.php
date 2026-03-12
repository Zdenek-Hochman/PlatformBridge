<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

/**
 * Konfigurační objekt pro PlatformBridge.
 *
 * Immutable value object obsahující všechny konfigurační hodnoty.
 *
 * @see PlatformBridgeBuilder Pro doporučený způsob vytváření konfigurace
 */
final  class PlatformBridgeConfig
{
    private ?string $secretKey;
    private ?int $resolvedTtl;

    public function __construct(
        private string $configPath,
        private string $viewsPath,
        private string $cachePath,
        private string $locale,
        private string $bridgeConfigPath,
        private string $assetUrl,
        private bool $useHmac = false,
        private ?int $paramsTtl = null,
    ) {
        $this->validatePaths();
        [$this->secretKey, $this->resolvedTtl] = $this->loadBridgeConfig();
    }

    /**
     * Načte konfiguraci z bridge-config.php.
     */
    private function loadBridgeConfig(): array
    {
        if (!$this->useHmac) {
            return [null, null];
        }

        $config = $this->requireBridgeConfig();

        $secretKey = $config['secretKey']
            ?? throw new \InvalidArgumentException("Bridge config must contain 'secretKey'.");

        $ttl = $this->paramsTtl ?? ($config['ttl'] ?? null);

        return [$secretKey, $ttl];
    }

    /**
     * Načte bridge-config.php a vrátí pole.
     * Funguje stejně na localhost i na serveru – soubor je vždy
     * v resources/config/bridge-config.php uvnitř balíčku.
     */
    private function requireBridgeConfig(): array
    {
        if (!file_exists($this->bridgeConfigPath)) {
            throw new \InvalidArgumentException(
                "Bridge config not found: {$this->bridgeConfigPath}"
            );
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $this->bridgeConfigPath;

        if (!is_array($config)) {
            throw new \InvalidArgumentException('Bridge config must return an array.');
        }

        return $config;
    }

    /**
     * Validuje existenci potřebných cest (adresáře s konfigurací a šablonami).
     *
     * @throws \InvalidArgumentException Pokud některá cesta neexistuje
     */
    private function validatePaths(): void
    {
		//TODO: Odamzat hardcode přepsát na cesty z builderu, které se sem předávají
		$this->configPath = "resources/defaults";
        if (!is_dir($this->configPath)) {
            throw new \InvalidArgumentException(
                "Config path does not exist: {$this->configPath}"
            );
        }

        if (!is_dir($this->viewsPath)) {
            throw new \InvalidArgumentException(
                "Views path does not exist: {$this->viewsPath}"
            );
        }
    }

    /**
     * Vrátí URL pro načítání assetů (JS/CSS).
     *
     * @return string URL k asset složce (např. '/public/platformbridge' nebo '/platformbridge')
     */
    public function getAssetUrl(): string
    {
        return $this->assetUrl;
    }

    /**
     * Vrátí URL k API endpointu.
     *
     * Odvozuje se ze stejné base URL jako assety:
     *   - Standalone: '/public/platformbridge/api.php'
     *   - Vendor: '/platformbridge/api.php'
     */
    public function getApiUrl(): string
    {
        return rtrim($this->assetUrl, '/') . '/api.php';
    }

    /**
     * Vrátí cestu ke složce s konfiguracemi.
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * Vrátí cestu ke složce se šablonami (views).
     *
     * @return string Absolutní cesta k šablonám.
     */
    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    /**
     * Vrátí cestu ke složce pro cache šablon.
     *
     * @return string Absolutní cesta ke cache.
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    // public function getLocale(): string
    // {
    //     return $this->locale;
    // }

    /**
     * Vrátí secret key pro HMAC podepisování parametrů.
     * Načítá se z bridge-config.php pokud je HMAC zapnutý.
     *
     * @return string|null Tajný klíč nebo null, pokud není nastaven nebo je HMAC vypnutý.
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
     */
    public function getParamsTtl(): ?int
    {
        return $this->resolvedTtl;
    }

    /**
     * Vrátí, zda je HMAC podepisování zapnuté.
     *
     * @return bool True pokud je HMAC zapnutý, jinak false.
     */
    public function isHmacEnabled(): bool
    {
        return $this->useHmac;
    }

    /**
     * Vrátí konfiguraci handlerů pro formulářová pole.
     *
     * @return array{handlers: class-string[], default: class-string} Pole tříd handlerů a výchozí handler
     *
     * @see \Zoom\PlatformBridge\Handler\HandlerRegistry
     */
    public function getHandlersConfig(): array
    {
        return [
            'handlers' => [
                \Zoom\PlatformBridge\Handler\Fields\RadioHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\SelectHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\CheckboxHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\TextareaHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\TickBoxHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\HiddenHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\TextHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\NumberHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\DateHandler::class,
                \Zoom\PlatformBridge\Handler\Fields\FileHandler::class,
            ],
            'default' => \Zoom\PlatformBridge\Handler\Fields\TextHandler::class,
        ];
    }
}
