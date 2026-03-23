<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

use Zoom\PlatformBridge\Config\PathResolver;
use Zoom\PlatformBridge\Config\InstallerConfig;

/**
 * Instalátor PlatformBridge balíčku (vendor-only).
 *
 * Zajišťuje publikování assetů (JS/CSS), API endpointu a konfigurace
 * do hostující aplikace. Funguje POUZE ve vendor režimu – v standalone/dev
 * režimu není instalace potřeba, vše se čte přímo ze zdrojových složek.
 *
 * Konfigurace cest:
 *   Pokud v kořeni hostitelské aplikace existuje soubor platformbridge.php,
 *   Installer z něj načte uživatelské cesty (kam instalovat assety, config apod.).
 *   Pokud soubor neexistuje, použijou se výchozí hardcodované cesty.
 *
 * Použití:
 *   - CLI: php vendor/bin/platformbridge install [--force] [--only=assets,config,...]
 *   - Programově: (new Installer())->install()
 *
 * CLI parametry:
 *   --force           Přepíše i existující konfigurační soubory
 *   --vendor          Vynutí vendor režim (obejde autodetekci podle složkové struktury).
 *                     Užitečné v CI/CD (TeamCity), kde složková struktura neodpovídá
 *                     standardní Composer instalaci.
 *   --only=<kroky>    Spustí jen vybrané kroky (čárkou oddělené):
 *                       init, assets, api, config, security, cache
 *
 * @see InstallerConfig
 */
final class Installer
{
    private PathResolver $paths;
    private StubPublisher $publisher;

    /** Přepsat i existující konfigurační soubory */
    private bool $force = false;

    /** Vynutit vendor režim (obejde autodetekci) */
    private ?bool $forceVendor = null;

    /** Pokud neprázdné, spustí jen vybrané kroky */
    private array $only = [];

    /** @var list<string> Povolené názvy kroků pro --only */
    private const ALLOWED_STEPS = ['init', 'dirs', 'assets', 'api', 'config', 'security', 'cache'];

    public function __construct(?string $packageRoot = null, ?bool $forceVendor = null)
    {
        $this->forceVendor = $forceVendor;
        $this->paths = new PathResolver($packageRoot, $forceVendor);
        $this->publisher = new StubPublisher();
    }

    // ─── CLI options ─────────────────────────────────────────

    /**
     * Nastaví --force (přepíše i existující konfigurační soubory).
     */
    public function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    /**
     * Vynutí vendor režim bez ohledu na autodetekci složkové struktury.
     * Užitečné v CI/CD prostředích (TeamCity), kde složková struktura
     * neodpovídá standardní Composer instalaci a autodetekce selhává.
     */
    public function setVendorMode(bool $vendor): self
    {
        $this->forceVendor = $vendor;
        // Přebuduj PathResolver s novým nastavením
        $this->paths = new PathResolver($this->paths->packageRoot(), $vendor);
        return $this;
    }

    /**
     * Nastaví --only filtr (spustí jen vybrané kroky).
     *
     * @param list<string> $steps Názvy kroků: assets, api, config, security, json, cache
     * @throws \InvalidArgumentException Pokud obsahuje neplatný krok
     */
    public function setOnly(array $steps): self
    {
        $invalid = array_diff($steps, self::ALLOWED_STEPS);
        if ($invalid !== []) {
            throw new \InvalidArgumentException(
                'Unknown install steps: ' . implode(', ', $invalid)
                . '. Allowed: ' . implode(', ', self::ALLOWED_STEPS)
            );
        }
        $this->only = $steps;
        return $this;
    }

    /**
     * Parsuje CLI argumenty z $argv a nastaví příslušné options.
     *
     * @param list<string> $argv Argumenty příkazové řádky (bez názvu skriptu a příkazu)
     */
    public function applyCliOptions(array $argv): self
    {
        foreach ($argv as $arg) {
            if ($arg === '--force') {
                $this->setForce(true);
                continue;
            }

            if ($arg === '--vendor') {
                $this->setVendorMode(true);
                continue;
            }

            if (str_starts_with($arg, '--only=')) {
                $value = substr($arg, strlen('--only='));
                $steps = array_filter(array_map('trim', explode(',', $value)));
                $this->setOnly($steps);
                continue;
            }
        }

        return $this;
    }

    // ─── Install / Update ────────────────────────────────────

    /**
     * Kompletní instalace – adresářová struktura + assety + API + konfigurace.
     * Konfigurace se NEPŘEPÍŠE pokud existuje (pokud není --force).
     *
     * Funguje v obou režimech:
     *   - Vendor: balíček v vendor/zoom/platform-bridge/, deploy do hostující aplikace
     *   - Standalone: balíček jako root projekt, deploy dle platformbridge.php
     *
     * Krok 'init' vždy nejdříve publikuje platformbridge.php do kořene projektu
     * (pokud neexistuje), aby následující kroky měly k dispozici konfiguraci cest.
     *
     * Kroky: init → dirs → assets → api → config → security → cache
     */
    public function install(): void
    {
        $this->info("PlatformBridge Installer");
        $this->info("========================");

        $mode = $this->paths->isVendor() ? 'vendor' : 'standalone';
        $this->info("Mode: {$mode}");

        if ($this->force) {
            $this->info("Flag: --force (overwrite configs)");
        }
        if ($this->only !== []) {
            $this->info("Flag: --only=" . implode(',', $this->only));
        }

        // Vypiš zdroj konfigurace cest
        $installerConfig = $this->paths->installerConfig();
        if ($installerConfig->hasCustomConfig()) {
            $this->info("Config: " . InstallerConfig::CONFIG_FILE . " (uživatelské cesty)");
        } else {
            $this->info("Config: výchozí cesty (" . InstallerConfig::CONFIG_FILE . " zatím nenalezen)");
        }
        $this->info("");

        // 1. Publikuj platformbridge.php (konfigurační mapa cest).
        //    Po publikování se znovu načte InstallerConfig, aby následující
        //    kroky pracovaly s cestami zvolenými uživatelem.
        $this->runStep('init', $this->publishInstallerConfig(...));

        // 2. Vytvoř adresářovou strukturu + publikuj soubory
        $this->runStep('dirs', $this->ensureDirectoryStructure(...));
        $this->runStep('assets', $this->publishAssets(...));
        $this->runStep('api', $this->publishApiEndpoint(...));
        $this->runStep('config', $this->publishConfig(...));
        $this->runStep('security', $this->publishSecurityConfig(...));
        $this->runStep('cache', $this->ensureCacheDir(...));

        $this->info("\n✅ PlatformBridge installed successfully!");
    }

    /**
     * Update – přepíše assety a API, ale NE konfiguraci a JSON.
     * Vyžaduje předchozí install (platformbridge.php musí existovat).
     */
    public function update(): void
    {
        $this->info("PlatformBridge Updater");
        $this->info("======================");

        if (!$this->paths->installerConfig()->hasCustomConfig()) {
            $this->info("");
            $this->info("  ⚠️  Soubor " . InstallerConfig::CONFIG_FILE . " nenalezen.");
            $this->info("     Nejprve spusťte install pro vytvoření konfigurace:");
            $this->info("       php vendor/bin/platformbridge install");
            $this->info("");
            return;
        }

        $mode = $this->paths->isVendor() ? 'vendor' : 'standalone';
        $this->info("Mode: {$mode}");
        $this->info("");

        $this->runStep('assets', $this->publishAssets(...));
        $this->runStep('api', $this->publishApiEndpoint(...));

        $this->info("\n✅ PlatformBridge updated!");
    }

    // ─── Publish kroky ───────────────────────────────────────

    /**
     * Publikuje platformbridge.php (konfigurační mapa cest) do kořene hostitelské aplikace.
     *
     * ⚠️  Tento soubor je uživatelem spravovaný – --force ho NIKDY nepřepíše.
     * Uživatel v něm mění cesty; přepsání výchozím stubem by jeho změny zničilo.
     * Soubor se publikuje POUZE pokud ještě neexistuje.
     *
     * Po publikování (nebo ověření existence) znovu načte PathResolver,
     * aby následující install kroky pracovaly s cestami z aktuálního souboru.
     */
    public function publishInstallerConfig(): void
    {
        $stub = $this->paths->stubInstallerConfigFile();
        $target = $this->paths->userInstallerConfigFile();
        $label = InstallerConfig::CONFIG_FILE;

        // NIKDY nepřepisuj – uživatel tento soubor upravuje ručně.
        // --force se na platformbridge.php nevztahuje (pouze na bridge-config, security-config apod.).
        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info(
            $written
            ? "  ✅ Published: {$label}"
            : "  ⏭️  Skipped:   {$label} (exists – user config preserved)"
        );

        if ($this->force && !$written) {
            $this->info("           ℹ️  --force does not overwrite {$label} (user-maintained file)");
        }

        // Znovu načti PathResolver – konfigurační soubor nyní existuje
        // a InstallerConfig z něj přečte uživatelské cesty pro zbylé kroky.
        $this->reloadPaths();
    }

    /**
     * Znovu načte PathResolver a InstallerConfig.
     *
     * Volá se po publikování platformbridge.php, aby install kroky
     * (dirs, assets, config, …) pracovaly s cestami z nového konfiguračního souboru.
     * Vypíše načtené cesty pro diagnostiku.
     */
    private function reloadPaths(): void
    {
        $this->paths = new PathResolver($this->paths->packageRoot(), $this->forceVendor);

        // Diagnostický výpis – ukáže, s jakými cestami installer pracuje
        $config = $this->paths->installerConfig();
        if ($config->hasCustomConfig()) {
            $this->info("");
            $this->info("  📋 Loaded paths from " . InstallerConfig::CONFIG_FILE . ":");
            $this->info("       assets_path:     " . $config->assetsPath());
            $this->info("       bridge_config:   " . $config->bridgeConfig());
            $this->info("       security_config: " . $config->securityConfig());
            $this->info("       cache_path:      " . $config->cachePath());
            $this->info("       api_file:        " . $config->apiFile());
            $this->info("");
        }
    }

    /**
     * Publikuje bridge-config.php do hostující aplikace.
     * Cílová cesta se čte z InstallerConfig (nebo výchozí).
     * Bez --force se existující soubor nepřepisuje.
     */
    public function publishConfig(): void
    {
        $stub = $this->paths->stubBridgeConfigFile();
        $target = $this->paths->userBridgeConfigFile();
        $label = $this->paths->installerConfig()->bridgeConfig();

        $written = $this->publisher->publish($stub, $target, overwrite: $this->force);
        $this->info(
            $written
            ? "  ✅ Published: {$label}"
            : "  ⏭️  Skipped:   {$label} (exists)"
        );
    }

    /**
     * Publikuje security-config.php do hostující aplikace.
     * Cílová cesta se čte z InstallerConfig (nebo výchozí).
     * Soubor se umístí MIMO public/ složku – přístupný pouze internímu jádru.
     * Bez --force se existující soubor nepřepisuje.
     */
    public function publishSecurityConfig(): void
    {
        $stub = $this->paths->stubSecurityConfigFile();
        $target = $this->paths->userSecurityConfigFile();
        $label = $this->paths->installerConfig()->securityConfig();

        $written = $this->publisher->publish($stub, $target, overwrite: $this->force);
        $this->info(
            $written
            ? "  ✅ Published: {$label}"
            : "  ⏭️  Skipped:   {$label} (exists)"
        );
    }

    /**
     * Publikuje dist/ assety do cílové složky (VŽDY přepíše).
     * Cílová složka se čte z InstallerConfig (nebo výchozí).
     */
    private function publishAssets(): void
    {
        $distPath = $this->paths->packageDistPath();
        $targetPath = $this->paths->publicAssetsPath();
        $label = $this->paths->installerConfig()->assetsPath();

        foreach (['js', 'css'] as $dir) {
            $source = $distPath . '/' . $dir;
            if (is_dir($source)) {
                $count = $this->publisher->publishDirectory($source, $targetPath . '/' . $dir, overwrite: true);
                $this->info("  ✅ Published: {$label}/{$dir}/ ({$count} files)");
            } else {
                $this->info("  ⚠️  Assets source not found: dist/{$dir}/");
                $this->info("     Run 'npm run build' first to generate assets.");
            }
        }
    }

    /**
     * Publikuje API endpoint.
     * Cílová cesta se čte z InstallerConfig (nebo výchozí).
     * Bez --force se existující soubor nepřepisuje.
     */
    private function publishApiEndpoint(): void
    {
        $stub = $this->paths->stubApiFile();
        $target = $this->paths->publicApiFile();
        $label = $this->paths->installerConfig()->apiFile();

        $written = $this->publisher->publish($stub, $target, overwrite: $this->force);
        $this->info(
            $written
            ? "  ✅ Published: {$label}"
            : "  ⏭️  Skipped:   {$label} (exists)"
        );
    }

    /**
     * Vytvoří kompletní adresářovou strukturu podle InstallerConfig.
     *
     * Projde všechny cesty z InstallerConfig a zajistí, že cílové adresáře
     * existují PŘED publikováním souborů. Tím se řeší situace, kdy:
     *   - Uživatel má vlastní platformbridge.php s nestandardními cestami
     *   - Některé publish kroky jsou přeskočeny (soubor existuje)
     *   - Aplikace potřebuje adresáře ještě před prvním install
     */
    private function ensureDirectoryStructure(): void
    {
        $config = $this->paths->installerConfig();
        $projectRoot = $this->paths->projectRoot();

        // Sbírka adresářů, které je potřeba vytvořit
        $dirs = [
            $config->assetsPath()                   => 'assets (JS/CSS)',
            dirname($config->bridgeConfig())        => 'bridge config',
            dirname($config->securityConfig())      => 'security config',
            $config->cachePath()                     => 'cache',
            dirname($config->apiFile())              => 'API endpoint',
        ];

        $created = 0;
        foreach ($dirs as $relDir => $label) {
            // Přeskoč "." (dirname vrací "." pokud cesta nemá parent)
            if ($relDir === '.' || $relDir === '') {
                continue;
            }
            $absDir = $projectRoot . '/' . $relDir;
            if (!is_dir($absDir)) {
                mkdir($absDir, 0755, true);
                $this->info("  📁 Created: {$relDir}/ ({$label})");
                $created++;
            }
        }

        if ($created === 0) {
            $this->info("  ✅ Directory structure: OK (all directories exist)");
        }
    }

    private function ensureCacheDir(): void
    {
        $cache = $this->paths->cachePath();
        $label = $this->paths->installerConfig()->cachePath();
        if (!is_dir($cache)) {
            mkdir($cache, 0755, true);
            $this->info("  ✅ Created: {$label}/");
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Spustí krok pouze pokud je v --only filteru (nebo pokud filtr není nastaven).
     */
    private function runStep(string $name, \Closure $callback): void
    {
        if ($this->only !== [] && !in_array($name, $this->only, true)) {
            return;
        }
        $callback();
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    // ─── Static helpers (zachovány pro zpětnou kompatibilitu) ────

    /**
     * Vrátí výchozí web-accessible URL pro assety.
     *
     * Dynamicky detekuje cestu z DOCUMENT_ROOT:
     *   - Standalone (dev): cesta k dist/ složce
     *   - Vendor (prod): cesta k public/platformbridge složce
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return string URL relativní k document root
     */
    public static function getDefaultAssetUrl(string $packageRoot): string
    {
        $isVendor = preg_match('#[\\\\/]vendor[\\\\/]#', $packageRoot) === 1;
        $projectRoot = $isVendor ? dirname($packageRoot, 3) : $packageRoot;

        if (php_sapi_name() !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $url = self::resolveAssetUrlFromDocRoot($projectRoot, $isVendor);
            if ($url !== null) {
                return $url;
            }
        }

        // Fallback:
        // Vendor → /platformbridge (předpokládá doc root = {projectRoot}/public)
        // Standalone → /dist (build output přímo z balíčku)
        return $isVendor ? '/platformbridge' : '/dist';
    }

    /**
     * Vypočítá URL k assets relativně k DOCUMENT_ROOT.
     *
     * Standalone (dev): resolvuje cestu k dist/ složce
     * Vendor (prod): resolvuje cestu k public/platformbridge složce
     *
     * @param string $projectRoot Absolutní cesta ke kořeni projektu
     * @param bool $isVendor Zda běžíme ve vendor režimu
     */
    private static function resolveAssetUrlFromDocRoot(string $projectRoot, bool $isVendor): ?string
    {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }

        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        // Cílová složka závisí na režimu:
        //   Vendor  → {projectRoot}/public/platformbridge
        //   Standalone → {projectRoot}/dist
        $targetSuffix = $isVendor
            ? DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'platformbridge'
            : DIRECTORY_SEPARATOR . 'dist';

        $assetsPath = $projectRoot . $targetSuffix;
        $realAssets = realpath($assetsPath);

        if ($realAssets !== false) {
            $realAssets = str_replace('\\', '/', rtrim($realAssets, '/\\'));
            if (str_starts_with($realAssets, $docRoot . '/')) {
                return substr($realAssets, strlen($docRoot));
            }
        }

        $realProject = realpath($projectRoot);
        if ($realProject === false) {
            return null;
        }
        $realProject = str_replace('\\', '/', rtrim($realProject, '/\\'));

        // Relativní suffix pro URL
        $urlSuffix = $isVendor ? '/public/platformbridge' : '/dist';

        if (str_starts_with($realProject, $docRoot . '/')) {
            $basePath = substr($realProject, strlen($docRoot));
            return $basePath . $urlSuffix;
        }

        if (str_starts_with($docRoot, $realProject . '/')) {
            $subPath = substr($docRoot, strlen($realProject));
            if (str_starts_with($urlSuffix, $subPath)) {
                return substr($urlSuffix, strlen($subPath));
            }
        }

        if ($realProject === $docRoot) {
            return $urlSuffix;
        }

        return null;
    }

    public function getPackageRoot(): string
    {
        return $this->paths->packageRoot();
    }

    public function getProjectRoot(): string
    {
        return $this->paths->projectRoot();
    }

    /**
     * Vrátí výchozí URL k API endpointu.
     *
     * Dynamicky detekuje cestu z DOCUMENT_ROOT:
     *   - Standalone (dev): cesta k resources/stubs/api.php
     *   - Vendor (prod): public/platformbridge/api.php
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return string URL relativní k document root
     */
    public static function getDefaultApiUrl(string $packageRoot): string
    {
        $vendorAutoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        $isVendor = file_exists($vendorAutoload);

        if ($isVendor) {
            // Vendor (prod): API je v public/platformbridge/api.php
            return rtrim(self::getDefaultAssetUrl($packageRoot), '/') . '/api.php';
        }

        // Standalone (dev): API endpoint je přímo v resources/stubs/api.php
        $projectRoot = $packageRoot;

        if (php_sapi_name() !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
            $realProject = realpath($projectRoot);

            if ($docRoot !== false && $realProject !== false) {
                $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));
                $realProject = str_replace('\\', '/', rtrim($realProject, '/\\'));

                if ($realProject === $docRoot) {
                    return '/resources/stubs/api.php';
                }

                if (str_starts_with($realProject, $docRoot . '/')) {
                    $basePath = substr($realProject, strlen($docRoot));
                    return $basePath . '/resources/stubs/api.php';
                }
            }
        }

        return '/resources/stubs/api.php';
    }
}
