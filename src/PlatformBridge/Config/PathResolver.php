<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

/**
 * Centrální resoluce cest – eliminuje pevné cesty v celém balíčku.
 *
 * Podporuje:
 *   - vendor režim: balíček v vendor/zoom/platform-bridge/
 *   - standalone režim: balíček jako root projekt (XAMPP dev)
 *
 * Konfigurovatelné cesty:
 *   Pokud v kořeni hostitelské aplikace existuje soubor platformbridge.php,
 *   PathResolver z něj při konstrukci načte uživatelské cesty (kam instalovat
 *   assety, config soubory apod.). Pokud soubor neexistuje, použijí se
 *   výchozí hardcodované cesty – zpětná kompatibilita je plně zachována.
 *
 * @see InstallerConfig
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
     * Konfigurace instalačních cest (z platformbridge.php nebo výchozí)
     */
    private readonly InstallerConfig $installerConfig;

    /**
     * Konstruktor nastaví cesty podle režimu použití.
     * @param string|null $packageRoot Volitelně lze předat kořen balíčku (pro testy nebo speciální případy)
     * @param bool|null $forceVendor Explicitně vynutí vendor (true) nebo standalone (false) režim.
     *                               null = autodetekce podle složkové struktury.
     *                               Užitečné v CI/CD (TeamCity), kde složková struktura neodpovídá
     *                               standardní Composer instalaci.
     */
    public function __construct(?string $packageRoot = null, ?bool $forceVendor = null)
    {
        // Pokud není zadán, vezme 3 úrovně nad aktuální složkou (tj. kořen balíčku)
        $this->packageRoot = $packageRoot ?? dirname(__DIR__, 3);
        // Zjistí, zda jsme ve vendor režimu: explicitně nastaveno → použij, jinak autodetekce
        $this->isVendor = $forceVendor ?? $this->detectVendorMode();
        // Pokud jsme ve vendor režimu, projectRoot je 3 úrovně nad balíčkem (tj. kořen hostitelské aplikace), jinak je shodný s packageRoot
        $this->projectRoot = $this->isVendor ? dirname($this->packageRoot, 3) : $this->packageRoot;
        // Načti konfiguraci cest z platformbridge.php (nebo výchozí hodnoty)
        $this->installerConfig = new InstallerConfig($this->projectRoot);
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
     * Vrací cestu ke složce s referenční konfigurací balíčku.
     * Poznámka: bridge-config.php se nyní načítá z resources/stubs/.
     */
    public function packageConfigPath(): string
    {
        return $this->packageRoot . '/resources/config';
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
     * Vrací cestu ke stub souboru bridge-config.php v balíčku.
     * Používá se jako zdroj při publikování konfigurace do hostitelské aplikace.
     */
    public function stubBridgeConfigFile(): string
    {
        return $this->packageStubsPath() . '/bridge-config.php';
    }

    /**
     * Vrací cestu ke stub souboru security-config.php v balíčku.
     * Používá se jako zdroj při publikování bezpečnostní konfigurace.
     */
    public function stubSecurityConfigFile(): string
    {
        return $this->packageStubsPath() . '/security-config.php';
    }

    /**
     * Vrací cestu ke stub souboru api.php v balíčku.
     * Používá se jako zdroj při publikování API endpointu.
     */
    public function stubApiFile(): string
    {
        return $this->packageStubsPath() . '/api.php';
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
     * Výchozí: {projectRoot}/config/platform-bridge
     * Konfigurovatelné přes platformbridge.php klíč 'json_path'.
     */
    public function userConfigPath(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->jsonPath();
    }

    /**
     * Vrací cestu k uživatelskému konfiguračnímu souboru bridge-config.php v hostitelské aplikaci.
     * Výchozí: {projectRoot}/public/bridge-config.php
     * Konfigurovatelné přes platformbridge.php klíč 'bridge_config'.
     * V standalone režimu: cesta neexistuje → fallback na resources/stubs/bridge-config.php
     */
    public function userBridgeConfigFile(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->bridgeConfig();
    }

    /**
     * Vrací cestu k uživatelskému bezpečnostnímu konfiguračnímu souboru.
     * Tento soubor NENÍ v public/ – je přístupný pouze internímu jádru.
     * Výchozí: {projectRoot}/config/security-config.php
     * Konfigurovatelné přes platformbridge.php klíč 'security_config'.
     * V standalone režimu: cesta neexistuje → fallback na resources/stubs/security-config.php
     */
    public function userSecurityConfigFile(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->securityConfig();
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
     * Výchozí: {projectRoot}/public/platformbridge
     * Konfigurovatelné přes platformbridge.php klíč 'assets_path'.
     * Používá se POUZE ve vendor režimu (produkce).
     */
    public function publicAssetsPath(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->assetsPath();
    }

    /**
     * Vrací cestu k publikovanému API endpointu v hostitelské aplikaci.
     * Výchozí: {projectRoot}/public/platformbridge/api.php
     * Konfigurovatelné přes platformbridge.php klíč 'api_file'.
     */
    public function publicApiFile(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->apiFile();
    }

    /**
     * Vrací cestu ke složce pro cache v hostitelské aplikaci.
     * Výchozí: {projectRoot}/var/cache
     * Konfigurovatelné přes platformbridge.php klíč 'cache_path'.
     */
    public function cachePath(): string
    {
        return $this->projectRoot . '/' . $this->installerConfig->cachePath();
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
     * Vrátí resolved cestu k uživatelské konfiguraci podle běhového režimu.
     *
     * Vendor režim:     {projectRoot}/config/platform-bridge (uživatel může overridovat)
     * Standalone režim: {packageRoot}/resources/config (výchozí stubs pro dev)
     */
    public function resolvedUserConfigPath(): string
    {
        if ($this->isVendor) {
            return $this->userConfigPath();
        }
        return $this->packageConfigPath();
    }

    /**
     * Vrátí cestu k bridge-config.php s fallbackem.
     * Priorita:
     *   1. Vendor: {projectRoot}/public/bridge-config.php (publikován install příkazem)
     *   2. Standalone/localhost: resources/stubs/bridge-config.php (výchozí šablona)
     */
    public function resolvedBridgeConfigFile(): string
    {
        $userFile = $this->userBridgeConfigFile();
        if (file_exists($userFile)) {
            return $userFile;
        }
        return $this->stubBridgeConfigFile();
    }

    /**
     * Vrátí cestu k security-config.php s fallbackem.
     * Priorita:
     *   1. Vendor: {projectRoot}/config/security-config.php (publikován install příkazem)
     *   2. Standalone/localhost: resources/stubs/security-config.php (výchozí šablona)
     */
    public function resolvedSecurityConfigFile(): string
    {
        $userFile = $this->userSecurityConfigFile();
        if (file_exists($userFile)) {
            return $userFile;
        }
        return $this->stubSecurityConfigFile();
    }

    public function isVendor(): bool
    {
        return $this->isVendor;
    }

    /**
     * Vrací instanci InstallerConfig s načtenými/výchozími cestami.
     */
    public function installerConfig(): InstallerConfig
    {
        return $this->installerConfig;
    }

    /**
     * Vrací cestu ke stub souboru platformbridge.php v balíčku.
     * Používá se jako zdroj při publikování konfigurační mapy do hostitelské aplikace.
     */
    public function stubInstallerConfigFile(): string
    {
        return $this->packageStubsPath() . '/platformbridge.php';
    }

    /**
     * Vrací cestu k souboru platformbridge.php v kořeni hostitelské aplikace.
     */
    public function userInstallerConfigFile(): string
    {
        return $this->projectRoot . '/' . InstallerConfig::CONFIG_FILE;
    }

    private function hasJsonFiles(string $dir): bool
    {
        return glob($dir . '/*.json') !== [];
    }
}
