<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge;

use Zoom\PlatformBridge\Config\PathResolver;

/**
 * Builder pro konfiguraci PlatformBridge instance.
 *
 * Umožňuje fluent konfiguraci všech cest a nastavení před sestavením immutable objektu PlatformBridgeConfig.
 *
 * @example
 * ```php
 * $bridge = PlatformBridgeBuilder::create()
 *     ->withConfigPath('/path/to/config')
 *     ->withViewsPath('/path/to/views')
 *     ->withCachePath('/path/to/cache')
 *     ->withSecretKey(true)
 *     ->withParamsTtl(3600)
 *     ->build();
 * ```
 *
 * Cesta k security-config.php se nastavuje automaticky přes PathResolver
 * a nelze ji uživatelsky přepsat.
 *
 * @see PlatformBridgeConfig
 */
final class PlatformBridgeBuilder
{
    private ?string $configPath = null;
    private ?string $viewsPath = null;
    private string $locale = 'cs';
    private bool $useHmac = false;
    private ?int $paramsTtl = null;

    // PathResolver se vytvoří jednou a sdílí
    private PathResolver $paths;

    public function __construct()
    {
        $this->paths = new PathResolver(dirname(__DIR__, 2));
    }

    /**
     * Nastaví cestu ke složce s JSON konfigurací
     * (blocks.json, layouts.json, generators.json).
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withConfigPath(string $path): self
    {
        $this->configPath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví cestu ke složce se šablonami (views).
     *
     * @param string $path Absolutní nebo relativní cesta
     * @return self
     */
    public function withViewsPath(string $path): self
    {
        $this->viewsPath = $this->normalizePath($path);
        return $this;
    }

    /**
     * Nastaví jazyk aplikace (locale).
     *
     * @param string $locale Kód jazyka (např. 'cs', 'en')
     * @return self
     */
    public function withLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Zapne/vypne HMAC podepisování parametrů (ochrana integrity dat).
     * Secret key se načte ze security-config.php.
     *
     * @param bool $enable Zapnout/vypnout HMAC (default: true)
     * @return self
     */
    public function withSecretKey(bool $enable = true): self
    {
        $this->useHmac = $enable;
        return $this;
    }

    /**
     * Nastaví TTL (time-to-live) pro podepsané parametry.
     * Pokud není nastaveno, parametry nikdy nevyprší.
     *
     * @param int $seconds Platnost v sekundách
     * @return self
     */
    public function withParamsTtl(int $seconds): self
    {
        $this->paramsTtl = $seconds;
        return $this;
    }

    /**
     * Sestaví a vrátí nakonfigurovanou instanci PlatformBridge.
     *
     * @return PlatformBridge
     * @throws \InvalidArgumentException Pokud chybí povinná konfigurace nebo jsou cesty neplatné
     *
     * Bezpečnost:
     *  - Pokud je HMAC zapnutý, musí být nastaven validní secret key v security-config.php.
     *  - TTL pomáhá chránit proti replay útokům, doporučuje se nastavit.
     */
    public function build(): PlatformBridge
    {
        // Re-create PathResolver to ensure we read the latest platformbridge.php.
        // Covers the case where the file was edited after the Builder was instantiated
        // (e.g., during the same long-running process or between create() and build()).
        $this->paths = new PathResolver($this->paths->packageRoot());

        $config = new PlatformBridgeConfig(
            configPath:         $this->resolveConfigPath(),
            viewsPath:          $this->resolveViewsPath(),
            cachePath:          $this->paths->cachePath(),
            assetUrl:           $this->resolveAssetUrl(),
            apiUrl:             $this->resolveApiUrl(),
            securityConfigPath: $this->paths->resolvedSecurityConfigFile(),
            locale:             $this->locale,
            useHmac:            $this->useHmac,
            paramsTtl:          $this->paramsTtl,
            pathResolver:       $this->paths,
        );

        return PlatformBridge::fromConfig($config);
    }

    /**
     * Resolví cestu ke konfiguraci (JSON soubory).
     *
     * Vendor režim: VŽDY PathResolver (platformbridge.php je single source of truth).
     *   Změna json_path v platformbridge.php se okamžitě projeví bez změny kódu aplikace.
     *   Explicitní withConfigPath() je ve vendor režimu ignorován – vše se řídí
     *   platformbridge.php, aby nebyl dvojí zdroj pravdy.
     *
     * Standalone režim: Explicitní withConfigPath() má přednost, jinak PathResolver.
     *
     * @return string Absolutní cesta ke složce s JSON konfigurací
     */
    private function resolveConfigPath(): string
    {
        if ($this->paths->isVendor()) {
            return $this->paths->resolvedConfigPath();
        }

        return $this->configPath ?? $this->paths->resolvedConfigPath();
    }

    /**
     * Resolví cestu ke složce se šablonami (views).
     *
     * Vendor režim: VŽDY package views (z balíčku).
     * Standalone režim: Explicitní withViewsPath() má přednost, jinak package views.
     *
     * @return string Absolutní cesta ke složce s views
     */
    private function resolveViewsPath(): string
    {
        if ($this->paths->isVendor()) {
            return $this->paths->packageViewsPath();
        }

        return $this->viewsPath ?? $this->paths->packageViewsPath();
    }

    /**
     * Normalizuje cestu - odstraní trailing slash a lomítka.
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR . '/\\');
    }

    /**
     * Vrátí výslednou URL pro assety.
     *
     * Dynamicky resolví URL z InstallerConfig (platformbridge.php) a DOCUMENT_ROOT.
     * Respektuje uživatelské cesty – pokud uživatel změní assets_path v
     * platformbridge.php, URL se automaticky přepočítá.
     *
     * Priorita:
     *   1. DOCUMENT_ROOT detekce z konfigurované cesty (publicAssetsPath / packageDistPath)
     *   2. Fallback: odvozeno z InstallerConfig.assetsPath() (strip 'public/' prefix)
     *
     * @return string URL ke složce s assety
     */
    private function resolveAssetUrl(): string
    {
        // Absolutní cesta k assets na disku (dle platformbridge.php)
        $targetPath = $this->paths->isVendor()
            ? $this->paths->publicAssetsPath()
            : $this->paths->packageDistPath();

        // Zkus resolvovat URL z DOCUMENT_ROOT
        $url = $this->resolveUrlFromDocRoot($targetPath);
        if ($url !== null) {
            return $url;
        }

        // Fallback: odvoď URL z konfigurované cesty
        if ($this->paths->isVendor()) {
            return $this->stripPublicPrefix($this->paths->installerConfig()->assetsPath());
        }

        return '/dist';
    }

    /**
     * Vrátí výslednou URL pro API endpoint.
     *
     * Dynamicky resolví URL z InstallerConfig (platformbridge.php) a DOCUMENT_ROOT.
     * Respektuje uživatelské cesty – pokud uživatel změní api_file v
     * platformbridge.php, URL se automaticky přepočítá.
     *
     * @return string URL k API endpointu
     */
    private function resolveApiUrl(): string
    {
        // Absolutní cesta k API souboru na disku (dle platformbridge.php)
        $targetPath = $this->paths->isVendor()
            ? $this->paths->publicApiFile()
            : $this->paths->stubApiFile();

        // Zkus resolvovat URL z DOCUMENT_ROOT
        $url = $this->resolveUrlFromDocRoot($targetPath);
        if ($url !== null) {
            return $url;
        }

        // Fallback: odvoď URL z konfigurované cesty
        if ($this->paths->isVendor()) {
            return '/' . ltrim($this->stripPublicPrefix($this->paths->installerConfig()->apiFile()), '/');
        }

        return '/resources/stubs/api.php';
    }

    /**
     * Resolví URL relativní k DOCUMENT_ROOT z absolutní cesty na disku.
     *
     * @param string $absolutePath Absolutní cesta k souboru/složce
     * @return string|null URL nebo null pokud nelze resolvovat
     */
    private function resolveUrlFromDocRoot(string $absolutePath): ?string
    {
        if (php_sapi_name() === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
            return null;
        }

        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }

        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        // Zkus realpath (preferováno – resolvuje symlinky)
        $realPath = realpath($absolutePath);
        if ($realPath !== false) {
            $realPath = str_replace('\\', '/', rtrim($realPath, '/\\'));
            if (str_starts_with($realPath, $docRoot . '/')) {
                return substr($realPath, strlen($docRoot));
            }
        }

        // Zkus normalizovanou cestu přímo (složka nemusí ještě existovat)
        $normalized = str_replace('\\', '/', rtrim($absolutePath, '/\\'));
        if (str_starts_with($normalized, $docRoot . '/')) {
            return substr($normalized, strlen($docRoot));
        }

        // Project root je pod nebo nad doc root
        $projectRoot = str_replace('\\', '/', rtrim($this->paths->projectRoot(), '/\\'));
        $realProject = realpath($this->paths->projectRoot());
        if ($realProject !== false) {
            $realProject = str_replace('\\', '/', rtrim($realProject, '/\\'));
        } else {
            $realProject = $projectRoot;
        }

        if (str_starts_with($realProject, $docRoot . '/') || $realProject === $docRoot) {
            // Project root je v doc root → relativní URL
            $basePath = ($realProject === $docRoot) ? '' : substr($realProject, strlen($docRoot));
            $relPath = $this->paths->isVendor()
                ? $this->stripPublicPrefix($this->paths->installerConfig()->assetsPath())
                : '/dist';
            return $basePath . $relPath;
        }

        return null;
    }

    /**
     * Odstraní prefix 'public/' z relativní cesty a vrátí URL tvar.
     *
     * Příklady:
     *   'public/platformbridge'     → '/platformbridge'
     *   'public/custom/assets'      → '/custom/assets'
     *   'web/assets'                → '/web/assets' (neznámý prefix ponechán)
     *
     * @param string $relativePath Relativní cesta z InstallerConfig
     * @return string URL tvar cesty
     */
    private function stripPublicPrefix(string $relativePath): string
    {
        // Běžné public složky v PHP frameworcích
        $publicPrefixes = ['public/', 'web/', 'www/', 'htdocs/', 'public_html/'];

        foreach ($publicPrefixes as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return '/' . substr($relativePath, strlen($prefix));
            }
        }

        // Žádný známý prefix → vrať jako URL od root
        return '/' . ltrim($relativePath, '/');
    }
}
