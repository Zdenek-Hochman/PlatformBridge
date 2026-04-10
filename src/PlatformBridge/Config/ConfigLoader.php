<?php

declare(strict_types=1);

namespace PlatformBridge\Config;

use PlatformBridge\Shared\Exception\ConfigException;
use PlatformBridge\Shared\Exception\FileException;
use PlatformBridge\Translator\VariableResolver;
use PlatformBridge\Shared\Utils\JsonUtils;
use PlatformBridge\Paths\PathResolver;

/**
 * Načítá konfigurační JSON soubory pro PlatformBridge.
 *
 * Podporuje fallback mechaniku řízenu přes {@see PathResolver}:
 *
 * Vendor režim (balíček nainstalován přes Composer):
 *   1. Uživatelský soubor chráněný (config/platform-bridge/*.json.php)
 *   2. Uživatelský soubor nechráněný (config/platform-bridge/*.json)
 *   → Pokud neexistuje → vyhodí výjimku (žádný tichý fallback na defaults).
 *
 * Standalone režim (dev/XAMPP):
 *   1. Uživatelský soubor chráněný (config/platform-bridge/*.json.php)
 *   2. Uživatelský soubor nechráněný (config/platform-bridge/*.json)
 *   3. Package default  (resources/defaults/*.json)
 *
 * Strategie: Pokud existuje uživatelský soubor → použij POUZE ten (full override).
 *            Pokud neexistuje a je vendor → chyba.
 *            Pokud neexistuje a je standalone → package default.
 *
 * Bezpečnost:
 *   Chráněné soubory (.json.php) obsahují PHP exit guard, který brání
 *   zobrazení JSON obsahu přes webový prohlížeč. Viz {@see JsonGuard}.
 */
final class ConfigLoader
{
    private const BLOCKS_FILE = 'blocks.json';
    private const LAYOUTS_FILE = 'layouts.json';
    private const GENERATORS_FILE = 'generators.json';

    /**
     * @param string $userConfigPath Cesta k uživatelské konfiguraci (může neexistovat)
     * @param PathResolver $pathResolver Centrální resolver cest (vendor detekce + package defaults)
     * @param ConfigValidator $validator Validátor konfigurací
     */
    public function __construct(
        private readonly string $userConfigPath,
        private readonly PathResolver $pathResolver,
        private readonly ConfigValidator $validator,
        private readonly VariableResolver $variableResolver
    ) {
    }

    /**
     * Načte a zvaliduje všechny konfigurační soubory.
     *
     * @return array{blocks: array, layouts: array, generators: array}
     *
     * @throws ConfigException
     */
    public function load(): array
    {
        // Načtení raw JSON dat s fallbackem
        $blocksJson = $this->loadJsonFile(self::BLOCKS_FILE);
        $layoutsJson = $this->loadJsonFile(self::LAYOUTS_FILE);
        $generatorsJson = $this->loadJsonFile(self::GENERATORS_FILE);

        // Validace jednotlivých souborů
        $blocks = $this->validator->validateBlocks($blocksJson, self::BLOCKS_FILE);
        $layouts = $this->validator->validateLayouts($layoutsJson, self::LAYOUTS_FILE);
        $generators = $this->validator->validateGenerators($generatorsJson, self::GENERATORS_FILE);

        // Cross-validace vztahů
        $this->validator->validateRelations($blocks, $layouts, $generators);

        return [
            'blocks' => $this->variableResolver->resolveArray($blocks),
            'layouts' => $this->variableResolver->resolveArray($layouts),
            'generators' => $this->variableResolver->resolveArray($generators),
        ];

    }

    /**
     * Načte a dekóduje konfigurační JSON soubor s fallbackem přes PathResolver.
     *
     * @throws FileException Pokud soubor neexistuje nebo je nečitelný
     * @throws \PlatformBridge\Shared\Exception\JsonException Pokud JSON je nevalidní
     */
    private function loadJsonFile(string $filename): array
    {
        $paths = $this->pathResolver->resolveConfigFiles($this->userConfigPath, $filename);

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return JsonUtils::readFile($path);
            }
        }

        throw FileException::notFound($filename);
    }
}
