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
final readonly class PlatformBridgeConfig
{
    private ?string $secretKey;
    private ?int $resolvedTtl;

    public function __construct(
        private string $configPath,
        private string $viewsPath,
        private string $cachePath,
        private string $locale,
        private string $bridgeConfigPath,
        private bool $useHmac = false,
        private ?int $paramsTtl = null,
    ) {
        $this->validatePaths();
        [$this->secretKey, $this->resolvedTtl] = $this->loadBridgeConfig();
    }

    /**
     * Načte konfiguraci z bridge-config.php.
     *
     * @return array{0: ?string, 1: ?int} [secretKey, ttl]
     *
     * @throws \InvalidArgumentException Pokud chybí nebo je neplatný config
     */
    private function loadBridgeConfig(): array
    {
        //Pokud je Hmac vypnutý, není potřeba načítat konfiguraci a můžeme vrátit null hodnoty
        if (!$this->useHmac) {
            return [null, null];
        }

        if (!file_exists($this->bridgeConfigPath)) {
            throw new \InvalidArgumentException(
                "Bridge config file does not exist: {$this->bridgeConfigPath}"
            );
        }

        // Definujeme konstantu pro bezpečnostní kontrolu v konfiguračním souboru
        if (!defined('BRIDGE_BOOTSTRAPPED')) {
            define('BRIDGE_BOOTSTRAPPED', true);
        }

        // Načteme konfiguraci z externího souboru, který musí vrátit pole s klíči 'secretKey' a volitelně 'ttl'
        $config = require $this->bridgeConfigPath;

        if (!is_array($config)) {
            throw new \InvalidArgumentException(
                "Bridge config must return an array."
            );
        }

        $secretKey = $config['secretKey'] ?? null;
        $ttl = $this->paramsTtl ?? ($config['ttl'] ?? null);

        if ($secretKey === null) {
            throw new \InvalidArgumentException(
                "Bridge config must contain 'secretKey'."
            );
        }

        return [$secretKey, $ttl];
    }

    /**
     * Validuje existenci potřebných cest (adresáře s konfigurací a šablonami).
     *
     * @throws \InvalidArgumentException Pokud některá cesta neexistuje
     */
    private function validatePaths(): void
    {
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
     * Vrátí cestu ke složce s JSON konfigurací (blocks, layouts, generators).
     *
     * @return string Absolutní cesta ke konfiguraci.
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
