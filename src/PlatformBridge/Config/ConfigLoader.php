<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Config\Exception\ConfigException;
use Zoom\PlatformBridge\Paths\PathResolver;
use Zoom\PlatformBridge\Security\JsonGuard;

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
    ) {}

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
            'blocks' => $blocks,
            'layouts' => $layouts,
            'generators' => $generators,
        ];

    }

    private function loadJsonFile(string $filename): array
    {
		$paths = $this->pathResolver->resolveConfigFiles($this->userConfigPath, $filename);

		foreach ($paths as $path) {
			if (file_exists($path)) {
				return $this->decodeJsonFile($path, $filename);
			}
		}

        throw ConfigException::fileNotFound($filename);
    }

	/**
	 * Načte a dekóduje JSON soubor do asociativního pole.
	 *
	 * Nejprve načte obsah souboru, odstraní případnou ochrannou PHP hlavičku
	 * ({@see JsonGuard}), a následně provede dekódování JSON s vyhazováním výjimek.
	 *
	 * Ověřuje také, že výsledná struktura je pole (objekt nebo pole v JSON).
	 *
	 * @param string $filePath Absolutní cesta k souboru na disku
	 * @param string $displayName Název souboru pro účely chybových hlášek
	 * @return array Dekódovaná JSON data jako asociativní pole
	 *
	 * @throws ConfigException Pokud soubor nelze načíst, JSON je nevalidní nebo má neočekávanou strukturu
	 */
    private function decodeJsonFile(string $filePath, string $displayName): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw ConfigException::fileNotFound($filePath);
        }

        $json = JsonGuard::strip($raw);

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ConfigException::invalidJson($filePath, $e->getMessage());
        }

        if (!is_array($data)) {
            throw ConfigException::invalidStructure(
                $displayName,
                'JSON root musí být objekt nebo pole.'
            );
        }

        return $data;
    }
}
