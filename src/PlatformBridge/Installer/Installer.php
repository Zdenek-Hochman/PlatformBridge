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
     * Publikuje bridge-config.php (bez přepisu).
     */
    public function publishConfig(): void
    {
        $stub = $this->paths->packageStubsPath() . '/bridge-config.php';
        $target = $this->paths->userBridgeConfigFile();

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: config/platform-bridge/bridge-config.php"
            : "  ⏭️  Skipped:   config/platform-bridge/bridge-config.php (exists)"
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
     * Publikuje dist/ assety do public/ (VŽDY přepíše).
     */
    private function publishAssets(): void
    {
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
     */
    private function publishApiEndpoint(): void
    {
        $stub = $this->paths->packageStubsPath() . '/api.php';
        $target = $this->paths->publicAssetsPath() . '/api.php';

        $written = $this->publisher->publish($stub, $target, overwrite: false);
        $this->info($written
            ? "  ✅ Published: public/platformbridge/api.php"
            : "  ⏭️  Skipped:   public/platformbridge/api.php (exists)"
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
     * Dynamicky detekuje cestu z DOCUMENT_ROOT ke složce
     * {projectRoot}/public/platformbridge.
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return string URL relativní k document root
     */
    public static function getDefaultAssetUrl(string $packageRoot): string
    {
        $vendorAutoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
        $isVendor = file_exists($vendorAutoload);
        $projectRoot = $isVendor ? dirname($packageRoot, 3) : $packageRoot;

        if (php_sapi_name() !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
            $url = self::resolveAssetUrlFromDocRoot($projectRoot);
            if ($url !== null) {
                return $url;
            }
        }

        return $isVendor ? '/platformbridge' : '/public/platformbridge';
    }

    /**
     * Vypočítá URL k assets relativně k DOCUMENT_ROOT.
     */
    private static function resolveAssetUrlFromDocRoot(string $projectRoot): ?string
    {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }

        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        $assetsPath = $projectRoot . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'platformbridge';
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

        if (str_starts_with($realProject, $docRoot . '/')) {
            $basePath = substr($realProject, strlen($docRoot));
            return $basePath . '/public/platformbridge';
        }

        if (str_starts_with($docRoot, $realProject . '/')) {
            $subPath = substr($docRoot, strlen($realProject));
            $fullPath = '/public/platformbridge';
            if (str_starts_with($fullPath, $subPath)) {
                return substr($fullPath, strlen($subPath));
            }
        }

        if ($realProject === $docRoot) {
            return '/public/platformbridge';
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
}
