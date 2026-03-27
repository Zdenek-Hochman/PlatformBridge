<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Paths;

use Zoom\PlatformBridge\Security\JsonGuard;

/**
 * Resolvuje absolutní cesty k souborům a složkám balíčku.
 *
 * Rozlišuje dva kořeny:
 *   - **packageRoot** — kde žije zdrojový kód balíčku (views, default configs)
 *   - **projectRoot** — kde žije hostující aplikace (cache, assets, published configs)
 *
 * Ve standalone režimu jsou oba kořeny totožné.
 * Ve vendor režimu: projectRoot = dirname(packageRoot, 3).
 *
 * Vytvářen přes {@see PathResolverFactory}.
 */
final class PathResolver
{
    /** Kořen balíčku (src, resources/views, resources/defaults) */
    private readonly string $packageRoot;

    /** Kořen hostující aplikace (cache, assets, published configs) */
    private readonly string $projectRoot;

    private readonly PathsConfig $pathsConfig;

    public function __construct(string $packageRoot, string $projectRoot, PathsConfig $config)
    {
        $this->packageRoot = rtrim($packageRoot, '/\\');
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->pathsConfig = $config;
    }

    // ─── Project-root paths (hostující aplikace) ─────────────

    public function cachePath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->cache();
    }

    public function assetsPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->assets();
    }

    public function apiFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->api();
    }

	public function securityConfigFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->security();
    }

	public function bridgeConfigFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->bridge();
    }

    // ─── Package-root paths (zdrojový kód balíčku) ───────────

    public function viewsPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
    }

    public function configPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'defaults';
    }

	// ─── Package paths (stubs, config) ───────────────────────

  	public function packageStubsPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'stubs';
    }

    /**
     * Vrátí kandidáty pro konfigurační soubor (chráněný + nechráněný).
     *
     * @return string[] Pole cest seřazených podle priority (chráněný první)
     */
    public function resolveConfigFiles(string $userPath, string $filename): array
    {
        return [
            $userPath . DIRECTORY_SEPARATOR . JsonGuard::protectedFilename($filename),
            $userPath . DIRECTORY_SEPARATOR . $filename,
        ];
    }

    // ─── Introspection ───────────────────────────────────────

    /**
     * Konfigurace cest (relativní cesty z platformbridge.json).
     *
     * Umožňuje provisioner třídám přistupovat ke konfiguračním
     * cestám bez vazby na konkrétní PathResolver.
     */
    public function pathsConfig(): PathsConfig
    {
        return $this->pathsConfig;
    }

    /**
     * Zda balíček běží jako vendor závislost (packageRoot ≠ projectRoot).
     */
    public function isVendor(): bool
    {
        return $this->packageRoot !== $this->projectRoot;
    }

    // /**
    //  * Kořen hostující aplikace (ve standalone = packageRoot).
    //  */
    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * Kořen balíčku (kde žije zdrojový kód).
     */
    public function packageRoot(): string
    {
        return $this->packageRoot;
    }
}
