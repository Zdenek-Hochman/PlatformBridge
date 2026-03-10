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
     * Detekce režimu:
     *   - Vendor: /platformbridge (předpokládá document root = public/)
     *   - Standalone: /{basePath}/public/platformbridge
     *
     * Base path se auto-detekuje z DOCUMENT_ROOT, takže pokud
     * projekt běží v podsložce (např. /ai/), URL bude /ai/public/platformbridge.
     */
    public static function getDefaultAssetUrl(string $packageRoot): string
    {
        // Detekce vendor režimu
        $vendorAutoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';

        if (file_exists($vendorAutoload)) {
            return '/platformbridge';
        }

        // Standalone - detekce base path (podsložka pod document root)
        $basePath = self::detectWebBasePath($packageRoot);
        return $basePath . '/public/platformbridge';
    }

    /**
     * Detekuje base path projektu relativně k document root webového serveru.
     *
     * Pokud je projekt ve složce /ai/ pod document root,
     * vrátí '/ai'. Pokud je přímo v root, vrátí ''.
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return string Base path (např. '/ai' nebo '')
     */
    private static function detectWebBasePath(string $packageRoot): string
    {
        // V CLI režimu není webový kontext
        if (php_sapi_name() === 'cli' || !isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
            return '';
        }

        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        $pkgRoot = realpath($packageRoot);

        if ($docRoot === false || $pkgRoot === false) {
            return '';
        }

        // Normalizace na forward slashes
        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));
        $pkgRoot = str_replace('\\', '/', rtrim($pkgRoot, '/\\'));

        // Pokud je balíček pod document root, odvodíme relativní cestu
        if (str_starts_with($pkgRoot, $docRoot . '/')) {
            return substr($pkgRoot, strlen($docRoot));
        }

        return '';
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
