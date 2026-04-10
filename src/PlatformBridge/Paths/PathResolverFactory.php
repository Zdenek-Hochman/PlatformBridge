<?php

declare(strict_types=1);

namespace PlatformBridge\Paths;

/**
 * Factory pro automatickou detekci vendor/standalone režimu a vytvoření PathResolveru.
 */
final class PathResolverFactory
{
    /**
     * Automaticky vytvoří instanci PathResolver podle umístění balíčku.
     *
     * Detekuje, zda je balíček spuštěn jako vendor závislost (v vendor/platformbridge/platform-bridge)
     * nebo jako standalone (přímo v projektu). Podle toho nastaví projectRoot a načte konfiguraci.
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return PathResolver Inicializovaný resolver s detekovanými cestami
     */
    public static function auto(string $packageRoot): PathResolver
    {
        // Zjistí, zda běžíme jako vendor závislost
        $isVendor = self::detectVendor($packageRoot);

        // Pokud vendor, projectRoot je o 3 úrovně výš (typicky root projektu), jinak packageRoot
        $projectRoot = $isVendor ? dirname($packageRoot, 3) : $packageRoot;

        // Načte konfiguraci cest z projektu
        $config = PathsLoader::load($projectRoot);

        // Vytvoří a vrátí PathResolver s detekovanými cestami
        return new PathResolver(
            packageRoot: $packageRoot,
            projectRoot: $projectRoot,
            config: $config,
        );
    }

    /**
     * Detekuje, zda je balíček spuštěn jako vendor závislost (tedy v vendor/platformbridge/platform-bridge).
     *
     * Kontroluje existenci autoload.php dvě úrovně nad balíčkem a porovnává cesty,
     * aby rozlišil mezi vendor a standalone režimem.
     *
     * @param string $packageRoot Absolutní cesta ke kořeni balíčku
     * @return bool True pokud je vendor, jinak standalone
     */
    private static function detectVendor(string $packageRoot): bool
    {
        // Očekávaná cesta k autoload.php v projektu
        $autoload = dirname($packageRoot, 2) . DIRECTORY_SEPARATOR . 'autoload.php';

        // Vendor režim: existuje autoload.php a kořen balíčku není shodný s kořenem projektu
        return file_exists($autoload) && realpath($packageRoot) !== realpath(dirname($packageRoot, 3));
    }
}