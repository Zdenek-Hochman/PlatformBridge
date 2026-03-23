<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Config\PathResolver;

/**
 * Konfigurační objekt pro PlatformBridge.
 *
 * Immutable value object obsahující všechny konfigurační hodnoty.
 *
 * @see PlatformBridgeBuilder Pro doporučený způsob vytváření konfigurace
 */
final class PlatformBridgeConfig
{
    private ?string $secretKey;
    private ?int $resolvedTtl;

    public function __construct(
        private string $configPath,
        private string $viewsPath,
        private string $cachePath,
        private string $assetUrl,
        private string $apiUrl,
        private string $securityConfigPath,
        private string $locale,
        private bool $useHmac = false,
        private ?int $paramsTtl = null,
        private ?PathResolver $pathResolver = null,
    ) {
        $this->validatePaths();
        [$this->secretKey, $this->resolvedTtl] = $this->loadSecurityConfig();
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
        if (!$this->useHmac) {
            return [null, null];
        }

        $config = $this->requireSecurityConfig();

        $secretKey = $config['secretKey'] ?? throw new \InvalidArgumentException("Security config must contain 'secretKey'.");

        $ttl = $this->paramsTtl ?? ($config['ttl'] ?? null);

        return [$secretKey, $ttl];
    }

    /**
     * Načte a vrátí pole z konfiguračního souboru security-config.php.
     *
     * Ověří existenci souboru, nastaví konstantu BRIDGE_BOOTSTRAPPED (pokud ještě není),
     * a vyžádá soubor. Pokud soubor neexistuje nebo nevrací pole, vyhodí výjimku.
     *
     * @return array Konfigurační pole se security nastavením
     * @throws \InvalidArgumentException Pokud soubor neexistuje nebo nevrací pole
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
        // Clear PHP stat cache to ensure we see the real filesystem state,
        // not stale cached results from a previous check in the same request.
        clearstatcache(true, $this->configPath);

        if (!is_dir($this->configPath)) {
            $diagnostic = $this->buildPathDiagnostic();
            $mismatchWarning = $this->buildPathMismatchWarning();
            throw new \InvalidArgumentException(
                "Config path does not exist: {$this->configPath}\n"
                . $diagnostic
                . $mismatchWarning
                . "Spusťte 'php vendor/bin/platformbridge install' pro vytvoření adresářové struktury,\n"
                . "nebo zkontrolujte konfiguraci v platformbridge.php (klíč 'json_path').\n"
                . "⚠️  Po změně cest v platformbridge.php vždy spusťte install znovu."
            );
        }

        clearstatcache(true, $this->viewsPath);

        if (!is_dir($this->viewsPath)) {
            throw new \InvalidArgumentException(
                "Views path does not exist: {$this->viewsPath}\n"
                . "Spusťte 'php vendor/bin/platformbridge install' nebo ověřte konfiguraci."
            );
        }
    }

    /**
     * Sestaví diagnostický řetězec pro chybovou hlášku.
     * Pomáhá identifikovat, odkud se cesta vzala a zda platformbridge.php
     * skutečně obsahuje očekávané hodnoty.
     */
    private function buildPathDiagnostic(): string
    {
        $resolver = $this->pathResolver;
        if ($resolver === null) {
            return '';
        }

        $lines = [];
        $installerConfig = $resolver->installerConfig();
        $configFile = $resolver->userInstallerConfigFile();

        $lines[] = "Diagnostika:";
        $lines[] = "  platformbridge.php: " . (file_exists($configFile) ? $configFile : 'NENALEZEN');
        $lines[] = "  json_path (z config): '" . $installerConfig->jsonPath() . "'";
        $lines[] = "  resolved config path: " . $resolver->resolvedConfigPath();
        $lines[] = "  actual config path:   " . $this->configPath;
        $lines[] = "  project root: " . $resolver->projectRoot();
        $lines[] = "  režim: " . ($resolver->isVendor() ? 'vendor' : 'standalone');
        $lines[] = "  custom config: " . ($installerConfig->hasCustomConfig() ? 'ANO' : 'NE (výchozí cesty)');
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Detekuje nesoulad mezi předanou configPath a cestou, kterou by PathResolver
     * vypočítal z aktuálního platformbridge.php. Pokud se liší, vrátí varování.
     *
     * Typický scénář: withConfigPath() nastaví starou cestu, ale platformbridge.php
     * byla mezitím editována na jinou json_path.
     */
    private function buildPathMismatchWarning(): string
    {
        $resolver = $this->pathResolver;
        if ($resolver === null) {
            return '';
        }

        $expectedPath = $resolver->resolvedConfigPath();
        $normalizedExpected = str_replace('\\', '/', rtrim($expectedPath, '/\\'));
        $normalizedActual   = str_replace('\\', '/', rtrim($this->configPath, '/\\'));

        if ($normalizedExpected === $normalizedActual) {
            return '';
        }

        return "⚠️  NESOULAD CES: configPath se liší od platformbridge.php!\n"
             . "     Předáno:    {$this->configPath}\n"
             . "     Očekáváno:  {$expectedPath}\n"
             . "     Pravděpodobná příčina: withConfigPath() přepisuje cestu z platformbridge.php.\n"
             . "     V vendor režimu odstraňte volání withConfigPath() – cesty řídí platformbridge.php.\n\n";
    }

    /**
     * Vrátí URL pro načítání assetů (JS/CSS).
     *
     * @return string URL k asset složce
     * @internal
     */
    public function getAssetUrl(): string
    {
        return $this->assetUrl;
    }

    /**
     * Vrátí URL k API endpointu.
     *
     * @return string URL k API endpointu
     * @internal
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Vrátí cestu ke složce se šablonami (views).
     *
     * @return string Absolutní cesta k šablonám.
     * @internal
     */
    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    /**
     * Vrátí cestu ke složce pro cache šablon.
     *
     * @return string Absolutní cesta ke cache.
     * @internal
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Vrátí instanci PathResolver pro centrální resolverování cest.
     *
     * @return PathResolver
     * @internal
     */
    public function getPathResolver(): PathResolver
    {
        return $this->pathResolver ??= new PathResolver(dirname(__DIR__, 2));
    }

    //TODO: Dodělat
    // public function getLocale(): string
    // {
    //     return $this->locale;
    // }

    /**
     * Vrátí secret key pro HMAC podepisování parametrů.
     * Načítá se ze security-config.php pokud je HMAC zapnutý.
     *
     * @return string|null Tajný klíč nebo null, pokud není nastaven nebo je HMAC vypnutý.
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
