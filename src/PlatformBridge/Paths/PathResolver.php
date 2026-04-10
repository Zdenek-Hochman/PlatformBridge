<?php

declare(strict_types=1);

namespace PlatformBridge\Paths;

use PlatformBridge\Security\JsonGuard;


/**
 * Třída PathResolver centralizuje správu cest k důležitým adresářům a souborům v rámci balíčku PlatformBridge i hostující aplikace.
 *
 * Umožňuje jednotně získávat absolutní cesty ke konfiguračním, cache, asset a dalším souborům, a to jak v režimu standalone,
 * tak při použití jako vendor závislost. Zajišťuje správné skládání cest podle aktuálního umístění balíčku a projektu.
 *
 * Hlavní funkce:
 * - Vrací cesty k adresářům a souborům projektu (cache, assets, configs, apod.)
 * - Vrací cesty k interním zdrojům balíčku (views, defaults, stubs)
 * - Umožňuje rozlišit, zda běží jako vendor nebo standalone
 * - Poskytuje kandidáty pro načtení konfiguračních souborů (chráněný i nechráněný)
 * - Umožňuje přístup ke konfiguračnímu objektu PathsConfig
 *
 * Všechny cesty jsou generovány dynamicky na základě zadaných kořenových adresářů a konfigurace.
 * Třída není určena k instancování mimo framework, používá se jako pomocná vrstva pro správu cest.
 */
final class PathResolver
{

    /**
     * Absolutní cesta ke kořeni balíčku PlatformBridge (adresář se zdrojovým kódem a výchozími zdroji).
     * Např. .../vendor/platformbridge/platform-bridge nebo .../src při standalone použití.
     */
    private readonly string $packageRoot;


    /**
     * Absolutní cesta ke kořeni hostující aplikace (adresář projektu, kde se ukládají cache, assets, konfigurace).
     * Může být shodná s packageRoot při standalone režimu.
     */
    private readonly string $projectRoot;


    /**
     * Objekt s relativními cestami k důležitým složkám a souborům (z platformbridge.json).
     * Uchovává konfiguraci cest pro projekt i balíček.
     */
    private readonly PathsConfig $pathsConfig;


    /**
     * Konstruktor nastaví kořenové cesty a konfigurační objekt.
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku (např. vendor/platformbridge/platform-bridge)
     * @param string $projectRoot Absolutní cesta ke kořeni projektu (aplikace)
     * @param PathsConfig $config  Objekt s relativními cestami (z platformbridge.json)
     */
    public function __construct(string $packageRoot, string $projectRoot, PathsConfig $config)
    {
        // Odstraní případné koncové lomítko pro konzistentní skládání cest
        $this->packageRoot = rtrim($packageRoot, '/\\');
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->pathsConfig = $config;
    }

    // ─── Project-root paths (hostující aplikace) ─────────────

    /**
     * Vrací absolutní cestu do adresáře pro cache soubory projektu.
     * Např. .../project/var/cache
     */
    public function cachePath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->cache();
    }

    /**
     * Vrací absolutní cestu do adresáře pro statická data (assets) projektu.
     * Např. .../project/assets
     */
    public function assetsPath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->assets();
    }

    /**
     * Vrací absolutní cestu ke generovanému API souboru projektu.
     * Např. .../project/dev/api.php
     */
    public function apiFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->api();
    }

    /**
     * Vrací absolutní cestu ke konfiguračnímu souboru zabezpečení projektu.
     * Např. .../project/dev/security-config.php
     */
    public function securityConfigFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->security();
    }

    /**
     * Vrací absolutní cestu ke konfiguračnímu souboru bridge projektu.
     * Např. .../project/dev/bridge-config.php
     */
    public function bridgeConfigFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . $this->pathsConfig->bridge();
    }

    // ─── Package-root paths (zdrojový kód balíčku) ───────────

    /**
     * Vrací absolutní cestu do adresáře s view šablonami balíčku.
     * Např. .../platformbridge/resources/views
     */
    public function viewsPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
    }

    /**
     * Vrací absolutní cestu do adresáře s výchozími konfiguračními soubory balíčku.
     * Např. .../platformbridge/resources/defaults
     */
    public function configPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'defaults';
    }

    /**
     * Vrací absolutní cestu do adresáře s překladovými soubory (lang).
     * Např. .../platformbridge/resources/lang
     */
    public function langPath(): string
    {
        return $this->packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang';
    }

	// ─── Package paths (stubs, config) ───────────────────────

    /**
     * Vrací absolutní cestu do adresáře se stubs (šablonami) balíčku.
     * Např. .../platformbridge/resources/stubs
     */
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
     * Vrací objekt s konfigurací relativních cest (z platformbridge.json).
     * Umožňuje třídám mimo PathResolver získat přímý přístup ke konfiguraci cest,
     * aniž by musely znát konkrétní implementaci PathResolveru.
     *
     * @return PathsConfig Konfigurační objekt s relativními cestami
     */
    public function pathsConfig(): PathsConfig
    {
        return $this->pathsConfig;
    }


    /**
     * Zjistí, zda balíček běží jako vendor závislost (tedy je nainstalován v projektu).
     * Pokud je kořen balíčku odlišný od kořene projektu, jedná se o vendor režim.
     *
     * @return bool True pokud běží jako vendor, jinak standalone
     */
    public function isVendor(): bool
    {
        return $this->packageRoot !== $this->projectRoot;
    }


    /**
     * Vrací absolutní cestu ke kořeni hostující aplikace (projektu).
     * V režimu standalone je shodná s packageRoot.
     *
     * @return string Absolutní cesta ke kořeni projektu
     */
    public function projectRoot(): string
    {
        return $this->projectRoot;
    }


    /**
     * Vrací absolutní cestu ke kořeni balíčku (adresář se zdrojovým kódem).
     *
     * @return string Absolutní cesta ke kořeni balíčku
     */
    public function packageRoot(): string
    {
        return $this->packageRoot;
    }
}
