<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Installer;

use Zoom\PlatformBridge\Config\PathResolver;

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
    private PathResolver $paths;
    private StubPublisher $publisher;

    public function __construct(?string $packageRoot = null)
    {
        $this->paths = new PathResolver($packageRoot);
        $this->publisher = new StubPublisher();
    }

    /**
     * Kompletní instalace – assety + API + konfigurace.
     * Konfigurace se NEPŘEPÍŠE pokud existuje.
     */
    public function install(): void
    {
        $this->info("PlatformBridge Installer");
        $this->info("========================");
        $this->info("Mode: " . ($this->paths->isVendor() ? 'vendor' : 'standalone'));
        $this->info("");

        $this->publishAssets();
        $this->publishApiEndpoint();
        $this->publishConfig();
        $this->publishSecurityConfig();
        $this->publishJson();
        $this->ensureCacheDir();

        $this->info("\n✅ PlatformBridge installed successfully!");
    }

    /**
     * Update – přepíše assety a API, ale NE konfiguraci a JSON.
     */
    public function update(): void
    {
        $this->info("PlatformBridge Updater");
        $this->info("======================");

        $this->publishAssets();
        $this->publishApiEndpoint();

        $this->info("\n✅ PlatformBridge updated!");
    }

    /**
     * Publikuje bridge-config.php do hostující aplikace (bez přepisu).
     * V standalone režimu se přeskočí – dev používá resources/stubs/ přímo.
     *
     * Vendor: {projectRoot}/public/bridge-config.php
     * Standalone: resources/stubs/bridge-config.php (bez kopírování)
     */
    public function publishConfig(): void
    {
        if (!$this->paths->isVendor()) {
            $this->info("  ⏭️  Standalone mode: using resources/stubs/bridge-config.php directly (skipped)");
            return;
        }

        $stub = $this->paths->stubBridgeConfigFile();
        $target = $this->paths->userBridgeConfigFile();

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: public/bridge-config.php"
            : "  ⏭️  Skipped:   public/bridge-config.php (exists)"
        );
    }

    /**
     * Publikuje security-config.php do hostující aplikace (bez přepisu).
     * Soubor se umístí MIMO public/ složku – přístupný pouze internímu jádru.
     * V standalone režimu se přeskočí – dev používá resources/stubs/ přímo.
     *
     * Vendor: {projectRoot}/config/security-config.php
     * Standalone: resources/stubs/security-config.php (bez kopírování)
     */
    public function publishSecurityConfig(): void
    {
        if (!$this->paths->isVendor()) {
            $this->info("  ⏭️  Standalone mode: using resources/stubs/security-config.php directly (skipped)");
            return;
        }

        $stub = $this->paths->stubSecurityConfigFile();
        $target = $this->paths->userSecurityConfigFile();

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: config/security-config.php"
            : "  ⏭️  Skipped:   config/security-config.php (exists)"
        );
    }

    /**
     * Publikuje JSON soubory (bez přepisu).
     */
    public function publishJson(): void
    {
        $defaults = $this->paths->packageDefaultsPath();
        $target = $this->paths->userJsonPath();

        foreach (['blocks.json', 'layouts.json', 'generators.json'] as $file) {
            $source = $defaults . '/' . $file;

            // Pokud zdrojový soubor neexistuje, přeskoč
            if (!file_exists($source)) {
                $this->info("  ⚠️  Source not found: {$file} (skipped)");
                continue;
            }

            $written = $this->publisher->publish(
                $source,
                $target . '/' . $file,
                overwrite: false
            );
            $this->info($written
                ? "  ✅ Published: config/platform-bridge/{$file}"
                : "  ⏭️  Skipped:   config/platform-bridge/{$file} (exists)"
            );
        }
    }

    /**
     * Publikuje dist/ assety do public/platformbridge/ (VŽDY přepíše).
     * V standalone režimu se přeskočí – dev používá dist/ přímo.
     */
    private function publishAssets(): void
    {
        if (!$this->paths->isVendor()) {
            $this->info("  ⏭️  Standalone mode: assets served from dist/ directly (skipped)");
            return;
        }

        $distPath = $this->paths->packageDistPath();
        $targetPath = $this->paths->publicAssetsPath();

        foreach (['js', 'css'] as $dir) {
            $source = $distPath . '/' . $dir;
            if (is_dir($source)) {
                $count = $this->publisher->publishDirectory($source, $targetPath . '/' . $dir, overwrite: true);
                $this->info("  ✅ Published: public/platformbridge/{$dir}/ ({$count} files)");
            } else {
                $this->info("  ⚠️  Assets source not found: dist/{$dir}/");
                $this->info("     Run 'npm run build' first to generate assets.");
            }
        }
    }

    /**
     * Publikuje API endpoint (bez přepisu).
     *
     * Vendor: {projectRoot}/public/platformbridge/api.php
     * Standalone: používá přímo resources/stubs/api.php (bez kopírování)
     */
    private function publishApiEndpoint(): void
    {
        if (!$this->paths->isVendor()) {
            $this->info("  ⏭️  Standalone mode: using resources/stubs/api.php directly (skipped)");
            return;
        }

        $stub = $this->paths->stubApiFile();
        $target = $this->paths->publicApiFile();
        $label = 'public/platformbridge/api.php';

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: {$label}"
            : "  ⏭️  Skipped:   {$label} (exists)"
        );
    }

    private function ensureCacheDir(): void
    {
        $cache = $this->paths->cachePath();
        if (!is_dir($cache)) {
            mkdir($cache, 0755, true);
            $this->info("  ✅ Created: var/cache/");
        }
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
