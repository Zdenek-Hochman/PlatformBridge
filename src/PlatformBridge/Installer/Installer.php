<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

/**
 * Instalátor PlatformBridge balíčku.
 *
 * Zajišťuje publikování assetů (JS/CSS), API endpointu a konfigurace
 * do hostující aplikace. Podporuje jak standalone režim (vývoj),
 * tak vendor režim (produkce).
 *
 * Použití:
 *   - CLI: php vendor/bin/platformbridge install
 *   - Programově: (new Installer())->install()
 */
final class Installer
{
    private string $packageRoot;
    private string $projectRoot;

    public function __construct(?string $packageRoot = null)
    {
        $this->packageRoot = $packageRoot ?? dirname(__DIR__, 3);
        $this->projectRoot = $this->detectProjectRoot();
    }

    /**
     * Detekce project root - pokud jsme ve vendor, root je 3 úrovně výš.
     */
    private function detectProjectRoot(): string
    {
        if ($this->isVendorInstall()) {
            return dirname($this->packageRoot, 3);
        }

        return $this->packageRoot;
    }

    /**
     * Zjistí, zda balíček běží z vendor/ složky.
     */
    public function isVendorInstall(): bool
    {
        $vendorAutoload = dirname($this->packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        return file_exists($vendorAutoload) && $this->packageRoot !== dirname($this->packageRoot, 3);
    }

    /**
     * Kompletní instalace - assets, API endpoint, konfigurace, cache.
     */
    public function install(): void
    {
        $this->info("PlatformBridge Installer");
        $this->info("========================");
        $this->info("Mode: " . ($this->isVendorInstall() ? 'vendor' : 'standalone'));
        $this->info("Package root: {$this->packageRoot}");
        $this->info("Project root: {$this->projectRoot}");
        $this->info("");

        $this->ensureCacheDir();
        $this->publishAssets();
        $this->publishApiEndpoint();
        $this->publishConfig();

        $this->info("");
        $this->info("PlatformBridge installed successfully!");
    }

    /**
     * Kompletní instalace včetně npm build.
     *
     * Provede:
     *   1. npm install (instalace node balíčků)
     *   2. npm run build (kompilace TS/SCSS do JS/CSS)
     *   3. Standardní install (publikování assetů, API, konfigurace)
     *
     * Toto je užitečné při prvním nasazení nebo v CI/CD pipeline,
     * kdy je potřeba vše provést jedním příkazem.
     */
    public function fullInstall(): void
    {
        $this->info("PlatformBridge Full Installer");
        $this->info("==============================");
        $this->info("Mode: " . ($this->isVendorInstall() ? 'vendor' : 'standalone'));
        $this->info("Package root: {$this->packageRoot}");
        $this->info("Project root: {$this->projectRoot}");
        $this->info("");

        $this->buildAssets();
        $this->info("");
        $this->install();
    }

    /**
     * Aktualizace - přepíše assety a API endpoint, ale NE konfiguraci.
     */
    public function update(): void
    {
        $this->info("PlatformBridge Updater");
        $this->info("======================");
        $this->info("");

        $this->publishAssets();
        $this->publishApiEndpoint();

        $this->info("");
        $this->info("PlatformBridge updated successfully!");
    }

    /**
     * Sestaví frontend assety (npm install + npm run build).
     *
     * Spustí npm install a npm run build v kořenu balíčku.
     * Vyžaduje nainstalovaný Node.js a npm.
     *
     * @param string $buildScript Název npm scriptu pro build (default: 'build')
     * @return bool True pokud build proběhl úspěšně
     */
    public function buildAssets(string $buildScript = 'build'): bool
    {
        $this->info("Building frontend assets...");
        $this->info("  Package root: {$this->packageRoot}");

        $packageJson = $this->packageRoot . DIRECTORY_SEPARATOR . 'package.json';
        if (!file_exists($packageJson)) {
            $this->info("  [WARNING] package.json not found in {$this->packageRoot}");
            $this->info("  Skipping frontend build.");
            return false;
        }

        // Zjistíme, zda je npm dostupný
        $npmVersion = $this->runCommand('npm --version');
        if ($npmVersion === null) {
            $this->info("  [ERROR] npm is not installed or not in PATH.");
            $this->info("  Install Node.js from https://nodejs.org/");
            return false;
        }
        $this->info("  npm version: " . trim($npmVersion));

        // npm install
        $this->info("  Running npm install...");
        $npmInstall = $this->runCommand('cd ' . escapeshellarg($this->packageRoot) . ' && npm install');
        if ($npmInstall === null) {
            $this->info("  [ERROR] npm install failed.");
            return false;
        }
        $this->info("  npm install completed.");

        // npm run build
        $this->info("  Running npm run {$buildScript}...");
        $npmBuild = $this->runCommand(
            'cd ' . escapeshellarg($this->packageRoot) . ' && npm run ' . escapeshellarg($buildScript)
        );
        if ($npmBuild === null) {
            $this->info("  [ERROR] npm run {$buildScript} failed.");
            return false;
        }
        $this->info("  npm run {$buildScript} completed.");
        $this->info("  Frontend assets built successfully!");

        return true;
    }

    /**
     * Spustí shell příkaz a vrátí výstup.
     *
     * @param string $command Příkaz ke spuštění
     * @return string|null Výstup příkazu, nebo null při chybě
     */
    private function runCommand(string $command): ?string
    {
        $output = [];
        $exitCode = 0;

        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            foreach ($output as $line) {
                $this->info("    > {$line}");
            }
            return null;
        }

        return implode("\n", $output);
    }

    /**
     * Vytvoří cache adresář pokud neexistuje.
     */
    private function ensureCacheDir(): void
    {
        $cacheDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
            $this->info("  Created cache directory: {$cacheDir}");
        }
    }

    /**
     * Publikuje JS a CSS soubory do public složky.
     *
     * Zdrojové soubory jsou v package: public/platformbridge/js/ a css/
     */
    private function publishAssets(): void
    {
        $source = $this->packageRoot . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'platformbridge';
        $target = $this->getPublicAssetsPath();

        if (!is_dir($source)) {
            $this->info("  [WARNING] Assets source not found: {$source}");
            $this->info("  Run 'npm run build' first to generate assets.");
            return;
        }

        // Kopírování JS souborů
        $jsSource = $source . DIRECTORY_SEPARATOR . 'js';
        if (is_dir($jsSource)) {
            $this->copyDirectory($jsSource, $target . DIRECTORY_SEPARATOR . 'js');
            $this->info("  Published JS assets to: {$target}" . DIRECTORY_SEPARATOR . "js");
        }

        // Kopírování CSS souborů
        $cssSource = $source . DIRECTORY_SEPARATOR . 'css';
        if (is_dir($cssSource)) {
            $this->copyDirectory($cssSource, $target . DIRECTORY_SEPARATOR . 'css');
            $this->info("  Published CSS assets to: {$target}" . DIRECTORY_SEPARATOR . "css");
        }
    }

    /**
     * Publikuje API endpoint do public složky.
     *
     * Ve standalone režimu: api.php už existuje v public/platformbridge/
     * Ve vendor režimu: kopíruje stub do host aplikace
     */
    private function publishApiEndpoint(): void
    {
        $target = $this->getPublicAssetsPath() . DIRECTORY_SEPARATOR . 'api.php';

        // Ve standalone režimu api.php už existuje
        if (!$this->isVendorInstall()) {
            if (file_exists($target)) {
                $this->info("  API endpoint: using existing {$target}");
                return;
            }
        }

        $stubSource = $this->packageRoot . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'api.php';

        if (!file_exists($stubSource)) {
            $this->info("  [WARNING] API stub not found: {$stubSource}");
            return;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Nepřepisovat existující api.php (uživatel mohl přidat vlastní logiku)
        if (file_exists($target)) {
            $this->info("  API endpoint already exists: {$target}");
            return;
        }

        copy($stubSource, $target);
        $this->info("  Published API endpoint to: {$target}");
    }

    /**
     * Publikuje konfigurační soubor (bridge-config.php) do projektu.
     * Nepřepisuje existující soubor.
     */
    private function publishConfig(): void
    {
        // Ve standalone režimu nepublikujeme - konfig je už v resources/
        if (!$this->isVendorInstall()) {
            $this->info("  Config: using local resources/config/bridge-config.php");
            return;
        }

        $target = $this->projectRoot . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'bridge-config.php';

        // Nepřepisovat existující konfiguraci
        if (file_exists($target)) {
            $this->info("  Config already exists: {$target}");
            return;
        }

        $stubSource = $this->packageRoot . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'bridge-config.php';

        if (!file_exists($stubSource)) {
            $this->info("  [WARNING] Config stub not found: {$stubSource}");
            return;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        copy($stubSource, $target);
        $this->info("  Published config to: {$target}");
    }

    /**
     * Vrátí cestu ke složce s public assety.
     *
     * V obou režimech: {projectRoot}/public/platformbridge/
     */
    public function getPublicAssetsPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'platformbridge';
    }

    /**
     * Vrátí výchozí web-accessible URL pro assety.
     *
     * Dynamicky detekuje cestu z DOCUMENT_ROOT ke složce
     * {projectRoot}/public/platformbridge. Funguje korektně v obou režimech:
     *
     *   - doc root = {projectRoot}/public → /platformbridge
     *   - doc root = {projectRoot}        → /public/platformbridge
     *   - doc root = /var/www, project = /var/www/app → /app/public/platformbridge
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return string URL relativní k document root
     */
    public static function getDefaultAssetUrl(string $packageRoot): string
    {
        // Detekce vendor režimu a project root
        $vendorAutoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        $isVendor = file_exists($vendorAutoload);
        $projectRoot = $isVendor ? dirname($packageRoot, 3) : $packageRoot;

        // Ve webovém kontextu detekujeme URL z DOCUMENT_ROOT
        if (php_sapi_name() !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $url = self::resolveAssetUrlFromDocRoot($projectRoot);
            if ($url !== null) {
                return $url;
            }
        }

        // CLI fallback - vendor předpokládá doc root = public/,
        // standalone předpokládá doc root = project root
        return $isVendor ? '/platformbridge' : '/public/platformbridge';
    }

    /**
     * Vypočítá URL k assets relativně k DOCUMENT_ROOT.
     *
     * Porovnává fyzickou cestu k assets ({projectRoot}/public/platformbridge)
     * s DOCUMENT_ROOT a vrací odpovídající URL cestu.
     *
     * @param string $projectRoot Absolutní cesta ke kořeni projektu
     * @return string|null URL k assets, nebo null pokud nelze detekovat
     */
    private static function resolveAssetUrlFromDocRoot(string $projectRoot): ?string
    {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }

        // Normalizace na forward slashes
        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        // 1) Pokud assets složka fyzicky existuje, použijeme ji přímo
        $assetsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'platformbridge';
        $realAssets = realpath($assetsPath);

        if ($realAssets !== false) {
            $realAssets = str_replace('\\', '/', rtrim($realAssets, '/\\'));
            if (str_starts_with($realAssets, $docRoot . '/')) {
                return substr($realAssets, strlen($docRoot));
            }
        }

        // 2) Assets neexistují - odvodíme z project root
        $realProject = realpath($projectRoot);
        if ($realProject === false) {
            return null;
        }
        $realProject = str_replace('\\', '/', rtrim($realProject, '/\\'));

        // Projekt je pod document root (doc root = /var/www, projekt = /var/www/app)
        if (str_starts_with($realProject, $docRoot . '/')) {
            $basePath = substr($realProject, strlen($docRoot));
            return $basePath . '/public/platformbridge';
        }

        // Document root je pod projektem (doc root = project/public)
        if (str_starts_with($docRoot, $realProject . '/')) {
            $subPath = substr($docRoot, strlen($realProject)); // např. '/public'
            $fullPath = '/public/platformbridge';
            if (str_starts_with($fullPath, $subPath)) {
                return substr($fullPath, strlen($subPath)); // → '/platformbridge'
            }
        }

        // Document root JE project root
        if ($realProject === $docRoot) {
            return '/public/platformbridge';
        }

        return null;
    }

    /**
     * Rekurzivní kopírování adresáře.
     */
    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $dest = $target . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
            } else {
                copy($item->getPathname(), $dest);
            }
        }
    }

    /**
     * Výpis informační zprávy.
     */
    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function getPackageRoot(): string
    {
        return $this->packageRoot;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }
}
