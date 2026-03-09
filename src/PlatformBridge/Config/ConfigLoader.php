<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Config\Exception\ConfigException;

/**
 * Načítá konfigurační JSON soubory pro PlatformBridge.
 *
 * Zodpovídá za:
 * - Načítání JSON souborů z disku
 * - Parsování JSON obsahu
 * - Validace pomocí ConfigValidator
 */
final class ConfigLoader
{
    private const BLOCKS_FILE = 'blocks.json';
    private const LAYOUTS_FILE = 'layouts.json';
    private const GENERATORS_FILE = 'generators.json';

    public function __construct(private readonly string $configDir, private readonly ConfigValidator $validator) {}

    /**
     * Načte a zvaliduje všechny konfigurační soubory.
     *
     * @return array{blocks: array, layouts: array, generators: array}
	 *
     * @throws ConfigException
     */
    public function load(): array
    {
        $this->validateConfigDir();

        // Načtení raw JSON dat
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
     * Načte pouze konkrétní soubor bez validace.
     *
     * Užitečné pro debugging nebo částečné načtení.
     *
     * @param string $filename Název souboru
	 *
     * @return array Raw data
	 *
     * @throws ConfigException
     */
    public function loadRaw(string $filename): array
    {
        $this->validateConfigDir();
        return $this->loadJsonFile($filename);
    }

    /**
     * Vrátí cestu ke konfiguračnímu adresáři.
	 *
	 * @return string Cesta ke konfiguračnímu adresáři
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

	/**
	 * Ověří, že konfigurační adresář existuje.
	 *
	 * @throws ConfigException Pokud adresář neexistuje
	 */
    private function validateConfigDir(): void
    {
        if (!is_dir($this->configDir)) {
            throw ConfigException::directoryNotFound($this->configDir);
        }
    }

	/**
	 * Načte a zparsuje JSON soubor z konfiguračního adresáře.
	 *
	 * @param string $filename Název souboru k načtení
	 *
	 * @return array Parsovaná data z JSON souboru
	 *
	 * @throws ConfigException Pokud soubor neexistuje, nelze ho přečíst nebo má neplatný JSON
	 */
    private function loadJsonFile(string $filename): array
    {
        $path = $this->configDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path)) {
            throw ConfigException::fileNotFound($path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw ConfigException::fileNotFound($path);
        }

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
