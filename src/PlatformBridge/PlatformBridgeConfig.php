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
        private string $securityConfigPath,
        private string $assetUrl,
        private string $apiUrl,
        private bool $useHmac = false,
        private ?int $paramsTtl = null,
    ) {
        $this->validatePaths();
        [$this->secretKey, $this->resolvedTtl] = $this->loadSecurityConfig();
    }

    /**
     * Načte bezpečnostní konfiguraci ze security-config.php.
     */
    private function loadSecurityConfig(): array
    {
        if (!$this->useHmac) {
            return [null, null];
        }

        $config = $this->requireSecurityConfig();

        $secretKey = $config['secretKey']
            ?? throw new \InvalidArgumentException("Security config must contain 'secretKey'.");

        $ttl = $this->paramsTtl ?? ($config['ttl'] ?? null);

        return [$secretKey, $ttl];
    }

    /**
     * Načte security-config.php a vrátí pole.
     * Soubor se načítá:
     *   - Standalone (localhost): z resources/stubs/security-config.php
     *   - Vendor (produkce): z {projectRoot}/config/security-config.php
     */
    private function requireSecurityConfig(): array
    {
        if (!file_exists($this->securityConfigPath)) {
            throw new \InvalidArgumentException(
                "Security config not found: {$this->securityConfigPath}"
            );
        }

        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        $config = require $this->securityConfigPath;

        if (!is_array($config)) {
            throw new \InvalidArgumentException('Security config must return an array.');
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
		// TODO: Odamzat hardcode přepsát na cesty z builderu, které se sem předávají
		// $this->configPath = "resources/defaults";
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
     * URL se resolvuje samostatně od asset URL:
     *   - Standalone (dev): '/resources/stubs/api.php'
     *   - Vendor (prod): '/public/platformbridge/api.php'
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
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
     * Načítá se ze security-config.php pokud je HMAC zapnutý.
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
