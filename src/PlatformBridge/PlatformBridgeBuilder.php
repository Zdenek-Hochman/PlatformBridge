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
     * Explicitní withConfigPath() má vždy přednost (v obou režimech).
     * Fallback: PathResolver::resolvedConfigPath() (standalone hledá JSON soubory
     * v uživatelské složce, jinak package defaults).
     *
     * @return string Absolutní cesta ke složce s JSON konfigurací
     */
    private function resolveConfigPath(): string
    {
        return $this->configPath ?? $this->paths->resolvedConfigPath();
    }

    /**
     * Resolví cestu ke složce se šablonami (views).
     *
     * Explicitní withViewsPath() má vždy přednost (v obou režimech).
     * Fallback: PathResolver::packageViewsPath() (šablony z balíčku).
     *
     * @return string Absolutní cesta ke složce s views
     */
    private function resolveViewsPath(): string
    {
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
     * Dynamicky resolví URL z InstallerConfig (platformbridge.json) a DOCUMENT_ROOT.
     * Respektuje uživatelské cesty – pokud uživatel změní assets_path v
     * platformbridge.json, URL se automaticky přepočítá.
     *
     * Priorita:
     *   1. DOCUMENT_ROOT detekce z konfigurované cesty (publicAssetsPath / packageDistPath)
     *   2. Fallback: odvozeno z InstallerConfig.assetsPath() (strip 'public/' prefix)
     *
     * @return string URL ke složce s assety
     */
    private function resolveAssetUrl(): string
    {
        // Určení cílové cesty k assetům na základě režimu (vendor vs standalone)
        $targetPath = $this->paths->isVendor() ? $this->paths->publicAssetsPath() : $this->paths->packageDistPath();

        // Pokus o výpočet URL relativní k DOCUMENT_ROOT
        $url = $this->resolveUrlFromDocRoot($targetPath);

        // Pokud byla URL úspěšně vypočítána, vrátí ji
        if ($url !== null) {
            return $url;
        }

        // Fallback pro vendor režim: odstranění prefixu 'public/' z cesty
        if ($this->paths->isVendor()) {
            return $this->stripPublicPrefix($this->paths->installerConfig()->assetsPath());
        }

        // Výchozí fallback: vrátí pevně danou cestu '/dist'
        return '/dist';
    }

    /**
     * Vrátí výslednou URL pro API endpoint.
     *
     * Dynamicky resolví URL z InstallerConfig (platformbridge.json) a DOCUMENT_ROOT.
     * Respektuje uživatelské cesty – pokud uživatel změní api_file v
     * platformbridge.json, URL se automaticky přepočítá.
     *
     * @return string URL k API endpointu
     */
    private function resolveApiUrl(): string
    {
        // Absolutní cesta k API souboru na disku (dle platformbridge.json)
        $targetPath = $this->paths->isVendor() ? $this->paths->publicApiFile() : $this->paths->stubApiFile();

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
     * Pokud cesta leží uvnitř webového rootu, vrátí relativní URL (např. /assets/file.js).
     * Pokud není možné URL určit (např. CLI režim, cesta mimo root), vrací null.
     *
     * @param string $absolutePath Absolutní cesta k souboru nebo složce
     * @return string|null Relativní URL nebo null pokud nelze určit
     */
    private function resolveUrlFromDocRoot(string $absolutePath): ?string
    {
        // 1) Pokud běžíme v CLI nebo není nastaven DOCUMENT_ROOT, nemá smysl řešit URL
        if (php_sapi_name() === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
            return null;
        }

        // 2) Získáme normalizovaný webový root
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot === false) {
            return null;
        }
        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        // 3) Normalizujeme zadanou cestu
        $abs = str_replace('\\', '/', rtrim($absolutePath, '/\\'));

        // 4) Pokud cesta začíná webovým rootem, vrátíme relativní část jako URL
        if (str_starts_with($abs, $docRoot . '/')) {
            return substr($abs, strlen($docRoot));
        }

        // 5) Jinak není cesta v rootu a URL určit neumíme
        return null;
    }

    /**
     * Převede relativní cestu k assetům na URL bez prefixu veřejné složky.
     *
     * Pokud cesta začíná některým z běžných veřejných prefixů (např. 'public/', 'web/', ...),
     * tento prefix odstraní a vrátí URL začínající lomítkem. Pokud prefix neodpovídá,
     * pouze přidá počáteční lomítko.
     *
     * Příklady:
     *   'public/platformbridge'   → '/platformbridge'
     *   'public/custom/assets'    → '/custom/assets'
     *   'web/assets'              → '/assets'
     *   'custom/assets'           → '/custom/assets'
     *
     * @param string $relativePath Relativní cesta k assetům (např. z InstallerConfig)
     * @return string URL vhodná pro použití ve webu
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
