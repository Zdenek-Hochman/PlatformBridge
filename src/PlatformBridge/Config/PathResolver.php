<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

/**
 * Centrální resoluce cest – eliminuje pevné cesty v celém balíčku.
 *
 * Podporuje:
 *   - vendor režim: balíček v vendor/zoom/platform-bridge/
 *   - standalone režim: balíček jako root projekt (XAMPP dev)
 */
final class PathResolver
{
    /**
     * Kořenová cesta balíčku (tj. složka platform-bridge)
     * Např. .../vendor/zoom/platform-bridge nebo root projektu v standalone režimu
     */
    private readonly string $packageRoot;

    /**
     * Kořenová cesta hostitelského projektu (aplikace)
     * V vendor režimu je to 3 úrovně nad balíčkem, jinak shodné s packageRoot
     */
    private readonly string $projectRoot;

    /**
     * Indikuje, zda běžíme ve vendor režimu (balíček je nainstalován přes Composer)
     */
    private readonly bool $isVendor;

    /**
     * Konstruktor nastaví cesty podle režimu použití.
     * @param string|null $packageRoot Volitelně lze předat kořen balíčku (pro testy nebo speciální případy)
     */
    public function __construct(?string $packageRoot = null)
    {
        // Pokud není zadán, vezme 3 úrovně nad aktuální složkou (tj. kořen balíčku)
        $this->packageRoot = $packageRoot ?? dirname(__DIR__, 3);
        // Zjistí, zda jsme ve vendor režimu (balíček je nainstalován přes Composer)
        $this->isVendor = $this->detectVendorMode();
        // Pokud jsme ve vendor režimu, projectRoot je 3 úrovně nad balíčkem (tj. kořen hostitelské aplikace), jinak je shodný s packageRoot
        $this->projectRoot = $this->isVendor ? dirname($this->packageRoot, 3) : $this->packageRoot;
    }

    /**
     * Zjistí, zda je balíček spuštěn ve vendor režimu (tj. nainstalován přes Composer).
     * Kontroluje existenci autoload.php dvě úrovně nad balíčkem a zároveň porovnává cesty,
     * aby rozlišil mezi vendor a standalone režimem.
     */
    private function detectVendorMode(): bool
    {
        // Očekávaná cesta k autoload.php ve vendor režimu
        $autoload = dirname($this->packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        // Vendor režim: existuje autoload.php a kořen balíčku není shodný s kořenem projektu
        return file_exists($autoload) && realpath($this->packageRoot) !== realpath(dirname($this->packageRoot, 3));
    }

    /**
     * Vrací absolutní cestu ke kořeni balíčku (platform-bridge).
     * Vždy ukazuje na složku balíčku, bez ohledu na režim.
     */
    public function packageRoot(): string
    {
        return $this->packageRoot;
    }

    /**
     * Vrací cestu ke složce s výchozí referenční konfigurací balíčku.
     * Typicky obsahuje bridge-config.php a další konfigurační soubory.
     */
    public function packageConfigPath(): string
    {
        return $this->packageRoot . '/config';
    }

    /**
     * Vrací cestu ke složce s výchozími JSON soubory (defaults) balíčku.
     */
    public function packageDefaultsPath(): string
    {
        return $this->packageRoot . '/resources/defaults';
    }

    /**
     * Vrací cestu ke složce s distribuovanými assety (JS/CSS) balíčku.
     */
    public function packageDistPath(): string
    {
        return $this->packageRoot . '/dist';
    }

    /**
     * Vrací cestu ke složce s view šablonami balíčku.
     */
    public function packageViewsPath(): string
    {
        return $this->packageRoot . '/resources/views';
    }

    /**
     * Vrací cestu ke složce se stubs soubory pro publish (kopírování do projektu).
     */
    public function packageStubsPath(): string
    {
        return $this->packageRoot . '/resources/stubs';
    }

    /**
     * Vrací absolutní cestu ke kořeni hostitelské aplikace (projektu).
     * V vendor režimu je to 3 úrovně nad balíčkem, v standalone režimu shodné s packageRoot.
     */
    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * Vrací cestu ke složce s uživatelskou konfigurací platform-bridge v hostitelské aplikaci.
     * Typicky: {projectRoot}/config/platform-bridge
     */
    public function userConfigPath(): string
    {
        return $this->projectRoot . '/config/platform-bridge';
    }

    /**
     * Vrací cestu k uživatelskému konfiguračnímu souboru bridge-config.php v hostitelské aplikaci.
     */
    public function userBridgeConfigFile(): string
    {
        return $this->userConfigPath() . '/bridge-config.php';
    }

    /**
     * Vrací cestu ke složce s uživatelskými JSON soubory v hostitelské aplikaci.
     * (Shodné s userConfigPath, pro čitelnost kódu.)
     */
    public function userJsonPath(): string
    {
        return $this->userConfigPath();
    }

    /**
     * Vrací cestu ke složce s public assety (JS/CSS) v hostitelské aplikaci.
     * Typicky: {projectRoot}/public/platformbridge
     * Používá se POUZE ve vendor režimu (produkce).
     */
    public function publicAssetsPath(): string
    {
        return $this->projectRoot . '/public/platformbridge';
    }

    /**
     * Vrací cestu ke složce pro dev API endpoint.
     * Typicky: {packageRoot}/resources/stubs
     * Používá se POUZE ve standalone režimu (localhost/dev).
     */
    public function devApiPath(): string
    {
        return $this->packageRoot . '/resources/stubs';
    }

    /**
     * Vrací cestu ke složce pro cache v hostitelské aplikaci.
     * Typicky: {projectRoot}/var/cache
     */
    public function cachePath(): string
    {
        return $this->projectRoot . '/var/cache';
    }

    /**
     * Vrátí cestu ke konfiguraci s fallbackem.
     * Priorita: user config → package defaults
     */
    public function resolvedConfigPath(): string
    {
        $userPath = $this->userConfigPath();
        if ($this->isVendor && is_dir($userPath) && $this->hasJsonFiles($userPath)) {
            return $userPath;
        }
        return $this->packageDefaultsPath();
    }

    /**
     * Vrátí cestu k bridge-config.php s fallbackem.
     * Priorita: user config → package config
     */
    public function resolvedBridgeConfigFile(): string
    {
        $userFile = $this->userBridgeConfigFile();
        if (file_exists($userFile)) {
            return $userFile;
        }
        return $this->packageConfigPath() . '/bridge-config.php';
    }

    public function isVendor(): bool
    {
        return $this->isVendor;
    }

    private function hasJsonFiles(string $dir): bool
    {
        return glob($dir . '/*.json') !== [];
    }
}
