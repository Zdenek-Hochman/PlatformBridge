<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Config\Exception\ConfigException;
use Zoom\PlatformBridge\Security\JsonGuard;

/**
 * Načítá konfigurační JSON soubory pro PlatformBridge.
 *
 * Podporuje fallback mechaniku:
 *   1. Uživatelský soubor chráněný (config/platform-bridge/*.json.php)
 *   2. Uživatelský soubor nechráněný (config/platform-bridge/*.json)
 *   3. Package default  (vendor/.../resources/defaults/*.json)
 *
 * Strategie: Pokud existuje uživatelský soubor → použij POUZE ten (full override).
 *            Pokud neexistuje → použij package default.
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
     * @param string $userConfigPath     Cesta k uživatelské konfiguraci (může neexistovat)
     * @param string $packageDefaultsPath Cesta k výchozím JSON souborům balíčku
     * @param ConfigValidator $validator   Validátor konfigurací
     */
    public function __construct(
        private readonly string $userConfigPath,
        private readonly string $packageDefaultsPath,
        private readonly ConfigValidator $validator,
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
            'blocks' => $blocks,
            'layouts' => $layouts,
            'generators' => $generators,
        ];
    }

    /**
     * Načte a zparsuje JSON soubor s fallbackem.
     *
     * Priorita:
     *   1. Uživatelský soubor chráněný (userConfigPath/filename.php)
     *   2. Uživatelský soubor nechráněný (userConfigPath/filename)
     *   3. Package default (packageDefaultsPath/filename)
     *
     * @param string $filename Název souboru k načtení (např. blocks.json)
     *
     * @return array Parsovaná data z JSON souboru
     *
     * @throws ConfigException Pokud soubor neexistuje ani ve fallbacku
     */
    private function loadJsonFile(string $filename): array
    {
        // 1. User override – chráněný formát (.json.php)
        $protectedFilename = JsonGuard::protectedFilename($filename);
        $userProtected = $this->userConfigPath . DIRECTORY_SEPARATOR . $protectedFilename;
        if (file_exists($userProtected)) {
            return $this->parseJsonFile($userProtected, $filename);
        }

        // 2. User override – nechráněný formát (.json)
        $userFile = $this->userConfigPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($userFile)) {
            return $this->parseJsonFile($userFile, $filename);
        }

        // 3. Package default
        $defaultFile = $this->packageDefaultsPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($defaultFile)) {
            return $this->parseJsonFile($defaultFile, $filename);
        }

        throw ConfigException::fileNotFound($filename);
    }

    /**
     * Parsuje JSON soubor.
     *
     * @param string $path     Absolutní cesta k souboru
     * @param string $filename Název souboru pro chybové hlášky
     *
     * @return array Parsovaná data
     *
     * @throws ConfigException
     */
    private function parseJsonFile(string $path, string $filename): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw ConfigException::fileNotFound($path);
        }

        // Odstraní PHP exit guard pokud je přítomen (.json.php formát)
        $content = JsonGuard::strip($content);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ConfigException::invalidJson($path, $e->getMessage());
        }

        if (!is_array($data)) {
            throw ConfigException::invalidStructure(
                $filename,
                "JSON root musí být objekt nebo pole."
            );
        }

        return $data;
    }
}
