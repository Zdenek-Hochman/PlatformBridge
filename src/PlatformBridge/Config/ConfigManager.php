<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Config\Exception\ConfigException;

/**
 * Centrální přístupový bod pro konfiguraci PlatformBridge.
 *
 * Odpovědnosti:
 * - Koordinace mezi ConfigLoader, ConfigResolver a ConfigValidator
 * - Lazy load konfigurace
 * - Poskytování API pouze pro čtení
 *
 * Deleguje:
 * - Načítání souborů → ConfigLoader
 * - Validaci → ConfigValidator
 * - Resolvování referencí → ConfigResolver
 */
final class ConfigManager
{
    private bool $loaded = false;

    /** @var array<string, array> */
    private array $blocks = [];

    /** @var array<string, array> */
    private array $layouts = [];

    /** @var array<string, array> */
    private array $generators = [];

    private ConfigLoader $loader;
    private ConfigValidator $validator;
    private ConfigResolver $resolver;

    /**
     * @param string $configDir Cesta ke složce s JSON konfiguracemi
     */
    public function __construct(private readonly string $configDir) {
        $this->validator = new ConfigValidator();
        $this->loader = new ConfigLoader($configDir, $this->validator);
        $this->resolver = new ConfigResolver();
    }

    /**
     * Tovární metoda - vytvoří a načte konfiguraci.
     */
    public static function create(string $configDir): self
    {
        $manager = new self($configDir);
        $manager->load();
        return $manager;
    }

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    /**
     * Načte konfiguraci ze souborů a předá data resolveru.
     *
     * @throws ConfigException
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $data = $this->loader->load();

        $this->blocks = $data['blocks'];
        $this->layouts = $data['layouts'];
        $this->generators = $data['generators'];

        // Předání surových dat resolveru
        $this->resolver->setData($this->blocks, $this->layouts, $this->generators);

        $this->loaded = true;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    // =========================================================================
    // GENERATORS
    // =========================================================================

    /**
     * Vrátí rozřešený generátor podle ID.
     *
     * @throws ConfigException Pokud generátor neexistuje
     */
    public function getGenerator(string $generatorId): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveGenerator($generatorId);
    }

    /**
     * Vrátí rozřešený generátor nebo null.
     */
    public function findGenerator(string $generatorId): ?array
    {
        $this->ensureLoaded();
        return $this->resolver->findResolvedGenerator($generatorId);
    }

    /**
     * Vrátí všechny rozřešené generátory.
     *
     * @return array<string, array>
     */
    public function getAllGenerators(): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveAllGenerators();
    }

    /**
     * Vrátí raw generátory (bez rozřešení).
     *
     * @return array<string, array>
     */
    public function getGenerators(): array
    {
        $this->ensureLoaded();
        return $this->generators;
    }

    public function getGeneratorLabel(string $generatorId): ?string
    {
        $generator = $this->findGenerator($generatorId);
        return $generator[ConfigKeys::LABEL->value] ?? null;
    }

    public function getGeneratorConfig(string $generatorId): ?array
    {
        $generator = $this->findGenerator($generatorId);
        return $generator[ConfigKeys::CONFIG->value] ?? null;
    }

    /**
     * Vrátí hodnotu z konfigurace generátoru podle cesty.
     *
     * @param string $path Cesta oddělená tečkami (např. "ai.model")
     */
    public function getConfigValue(string $generatorId, string $path, mixed $default = null): mixed
    {
        $config = $this->getGeneratorConfig($generatorId);

        if (!is_array($config)) {
            return $default;
        }

        foreach (explode('.', $path) as $key) {
            if (!is_array($config) || !array_key_exists($key, $config)) {
                return $default;
            }
            $config = $config[$key];
        }

        return $config;
    }

    public function hasGenerator(string $generatorId): bool
    {
        $this->ensureLoaded();
        return isset($this->generators[$generatorId]);
    }

    /**
     * @return string[]
     */
    public function getGeneratorIds(): array
    {
        $this->ensureLoaded();
        return array_keys($this->generators);
    }

    // =========================================================================
    // LAYOUTS
    // =========================================================================

    /**
     * Vrátí rozřešený layout podle ID.
     *
     * @throws ConfigException Pokud layout neexistuje
     */
    public function getLayout(string $layoutId): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveLayout($layoutId);
    }

    /**
     * Vrátí rozřešený layout nebo null.
     */
    public function findLayout(string $layoutId): ?array
    {
        $this->ensureLoaded();
        return $this->resolver->findResolvedLayout($layoutId);
    }

    /**
     * Vrátí raw layouty (bez rozřešení).
     *
     * @return array<string, array>
     */
    public function getLayouts(): array
    {
        $this->ensureLoaded();
        return $this->layouts;
    }

    /**
     * Vrátí sekce rozřešeného layoutu.
     */
    public function getResolvedSections(string $layoutId): array
    {
        $this->ensureLoaded();
        return $this->resolver->resolveLayoutSections($layoutId);
    }

    /**
     * Vrátí raw sekce layoutu (bez rozřešení bloků).
     */
    public function getSections(string $layoutId): array
    {
        $this->ensureLoaded();

        if (!isset($this->layouts[$layoutId])) {
            return [];
        }

        return $this->layouts[$layoutId][ConfigKeys::SECTIONS->value] ?? [];
    }

    /**
     * Vrátí bloky v sekci layoutu podle ID sekce.
     */
    public function getSectionBlocks(string $layoutId, string $sectionId): array
    {
        $sections = $this->getResolvedSections($layoutId);

        foreach ($sections as $section) {
            if (($section[ConfigKeys::ID->value] ?? null) === $sectionId) {
                return $section[ConfigKeys::BLOCKS->value] ?? [];
            }
        }

        return [];
    }

    /**
     * Vrátí bloky v sekci layoutu podle indexu.
     */
    public function getSectionBlocksByIndex(string $layoutId, int $sectionIndex): array
    {
        $sections = $this->getResolvedSections($layoutId);

        if (!isset($sections[$sectionIndex])) {
            return [];
        }

        return $sections[$sectionIndex][ConfigKeys::BLOCKS->value] ?? [];
    }

    /**
     * Najde index sekce podle ID.
     */
    public function findSectionIndex(string $layoutId, string $sectionId): ?int
    {
        $sections = $this->getResolvedSections($layoutId);

        foreach ($sections as $index => $section) {
            if (($section[ConfigKeys::ID->value] ?? null) === $sectionId) {
                return $index;
            }
        }

        return null;
    }

    public function hasLayout(string $layoutId): bool
    {
        $this->ensureLoaded();
        return isset($this->layouts[$layoutId]);
    }

    /**
     * @return string[]
     */
    public function getLayoutIds(): array
    {
        $this->ensureLoaded();
        return array_keys($this->layouts);
    }

    // =========================================================================
    // BLOCKS
    // =========================================================================

    /**
     * Vrátí blok podle ID.
     *
     * @throws ConfigException Pokud blok neexistuje
     */
    public function getBlock(string $blockId): array
    {
        $this->ensureLoaded();

        if (!isset($this->blocks[$blockId])) {
            throw ConfigException::invalidReference('block', $blockId);
        }

        return $this->blocks[$blockId];
    }

    /**
     * Vrátí blok nebo null.
     */
    public function findBlock(string $blockId): ?array
    {
        $this->ensureLoaded();
        return $this->blocks[$blockId] ?? null;
    }

    /**
     * Vrátí všechny bloky.
     *
     * @return array<string, array>
     */
    public function getBlocks(): array
    {
        $this->ensureLoaded();
        return $this->blocks;
    }

    /**
     * Vrátí všechny rozřešené bloky napříč layouty.
     */
    public function getAllResolvedBlocks(): array
    {
        $this->ensureLoaded();
        $result = [];

        foreach (array_keys($this->layouts) as $layoutId) {
            $sections = $this->getResolvedSections($layoutId);

            foreach ($sections as $sectionIndex => $section) {
                $blocks = $section[ConfigKeys::BLOCKS->value] ?? [];

                foreach ($blocks as $block) {
                    $result[] = [
                        'layout' => $layoutId,
                        'section' => $sectionIndex,
                        'block' => $block,
                    ];
                }
            }
        }

        return $result;
    }

    public function hasBlock(string $blockId): bool
    {
        $this->ensureLoaded();
        return isset($this->blocks[$blockId]);
    }

    /**
     * @return string[]
     */
    public function getBlockIds(): array
    {
        $this->ensureLoaded();
        return array_keys($this->blocks);
    }

}
