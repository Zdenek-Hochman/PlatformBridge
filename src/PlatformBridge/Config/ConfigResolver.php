<?php

declare(strict_types=1);

namespace PlatformBridge\Config;

use PlatformBridge\Shared\Exception\ConfigException;

/**
 * Resolver konfiguračních struktur PlatformBridge.
 *
 * Účel:
 * - rozbalení block referencí (`ref`)
 * - převod layoutů na resolved struktury
 * - propojení generators s resolved layouty
 * - cachování již rozřešených výsledků
 *
 * Terminologie:
 * - RAW = původní konfigurace načtená ze souborů
 * - RESOLVED = struktura s expandovanými referencemi
 *
 * Životní cyklus:
 * - třída je stateful
 * - volání {@see setData()} invaliduje všechny interní cache
 *
 * @package PlatformBridge\Config
 */
final class ConfigResolver
{
    /** @var array<string, array> Cache resolved layoutů */
    private array $resolvedLayouts = [];

    /** @var array<string, array> Cache resolved generators */
    private array $resolvedGenerators = [];

    /** @var array<string, array> Raw blocks (klíč => definice) */
    private array $blocks = [];

    /** @var array<string, array> Raw layouty */
    private array $layouts = [];

    /** @var array<string, array> Raw generators */
    private array $generators = [];

     /**
     * Nastaví raw konfigurační data.
     *
     * POZOR:
     * Volání této metody invaliduje všechny interní cache.
     *
     * @param array<string, array> $blocks Raw definice bloků
     * @param array<string, array> $layouts Raw layouty
     * @param array<string, array> $generators Raw generators
     *
     * @return void
     */
    public function setData(array $blocks, array $layouts, array $generators): void
    {
        $this->blocks = $blocks;
        $this->layouts = $layouts;
        $this->generators = $generators;

        // Při změně dat invalidujeme cache
        $this->resolvedLayouts = [];
        $this->resolvedGenerators = [];
    }

    // =========================================================================
    // GENERATORS
    // =========================================================================

    /**
     * Vrátí resolved generator podle ID.
     *
     * Garance:
     * - pokud existuje layout reference, bude resolved
     * - výsledek je uložen do cache
     *
     * @param string $generatorId ID generatoru
     *
     * @return array Resolved struktura generatoru
     *
     * @throws ConfigException Pokud generator neexistuje
     */
    public function resolveGenerator(string $generatorId): array
    {
        if (!isset($this->generators[$generatorId])) {
            throw ConfigException::invalidReference('generator', $generatorId);
        }

        if (!isset($this->resolvedGenerators[$generatorId])) {
            $this->resolvedGenerators[$generatorId] = $this->buildResolvedGenerator($generatorId);
        }

        return $this->resolvedGenerators[$generatorId];
    }

    /**
     * Vrátí resolved generator nebo null pokud neexistuje.
     *
     * @param string $generatorId ID generatoru
     *
     * @return array|null Resolved generator nebo null
	 */
    public function findResolvedGenerator(string $generatorId): ?array
    {
        if (!isset($this->generators[$generatorId])) {
            return null;
        }

        return $this->resolveGenerator($generatorId);
    }

    // =========================================================================
    // LAYOUTS
    // =========================================================================

    /**
     * Vrátí resolved layout podle ID.
     *
     * Všechny sekce a block reference jsou expandované.
     *
     * @param string $layoutId ID layoutu
     *
     * @return array Resolved struktura layoutu
     *
     * @throws ConfigException Pokud layout neexistuje
     */
    public function resolveLayout(string $layoutId): array
    {
        if (!isset($this->layouts[$layoutId])) {
            throw ConfigException::invalidReference('layout', $layoutId);
        }

        if (!isset($this->resolvedLayouts[$layoutId])) {
            $this->resolvedLayouts[$layoutId] = $this->buildResolvedLayout($this->layouts[$layoutId]);
        }

        return $this->resolvedLayouts[$layoutId];
    }

    /**
     * Vrátí resolved layout nebo null.
     *
     * @param string $layoutId ID layoutu
     *
     * @return array|null Resolved layout nebo null
     */
    public function findResolvedLayout(string $layoutId): ?array
    {
        if (!isset($this->layouts[$layoutId])) {
            return null;
        }

        return $this->resolveLayout($layoutId);
    }

    /**
     * Vrátí pouze resolved sekce layoutu.
     *
     * Pomocná metoda využívaná runtime rendererem.
     *
     * @param string $layoutId ID layoutu
     *
     * @return array<int, array> Pole resolved sekcí
     */
    public function resolveLayoutSections(string $layoutId): array
    {
        $layout = $this->findResolvedLayout($layoutId);
        return $layout[ConfigKeys::SECTIONS->value] ?? [];
    }

    // =========================================================================
    // BLOCKS
    // =========================================================================

     /**
     * Rozřeší block referenci a aplikuje overridy.
     *
     * Chování:
     * - načte block podle ID
     * - doplní ID pokud chybí
     * - odstraní `ref` z overrides
     * - aplikuje overridy pomocí array merge
     *
     * @param string $ref ID block reference
     * @param array $overrides Hodnoty pro přepsání
     *
     * @return array Resolved definice blocku
     *
     * @throws ConfigException Pokud block neexistuje
     *
     * @example
     * $resolver->resolveBlockReference('template_id', ["ref" => "template_id", "row_span" => 2]);
     */
    public function resolveBlockReference(string $ref, array $overrides = []): array
    {
        if (!isset($this->blocks[$ref])) {
            throw ConfigException::invalidReference('block', $ref);
        }

        $block = $this->blocks[$ref];

        if (!isset($block[ConfigKeys::ID->value])) {
            $block[ConfigKeys::ID->value] = $ref;
        }

        unset($overrides[ConfigKeys::REF->value]);

        if (!empty($overrides)) {
            $block = array_merge($block, $overrides);
        }

        return $block;
    }

    // =========================================================================
    // PRIVATE - BUILDING RESOLVED STRUCTURES
    // =========================================================================

    /**
     * Vytvoří resolved strukturu generatoru.
     *
     * Pokud generator obsahuje layout reference,
     * layout je resolved a vložen do výsledku.
     *
     * @param string $generatorId ID generatoru
     *
     * @return array Resolved generator
     */
    private function buildResolvedGenerator(string $generatorId): array
    {
        $generator = $this->generators[$generatorId];
        $layoutRef = $generator[ConfigKeys::LAYOUT_REF->value] ?? null;

        $resolvedLayout = null;
        if ($layoutRef !== null && isset($this->layouts[$layoutRef])) {
            $resolvedLayout = $this->resolveLayout($layoutRef);
        }

        return [
            ConfigKeys::ID->value => $generator[ConfigKeys::ID->value] ?? $generatorId,
            ConfigKeys::LABEL->value => $generator[ConfigKeys::LABEL->value] ?? null,
            ConfigKeys::LAYOUT_REF->value => $layoutRef,
            ConfigKeys::LAYOUT->value => $resolvedLayout,
            ConfigKeys::CONFIG->value => $generator[ConfigKeys::CONFIG->value] ?? [],
        ];
    }

	/**
     * Vytvoří resolved strukturu layoutu.
     * Resolve všech sekcí a jejich block definic.
     *
     * @param array $layout Raw layout
     *
     * @return array Resolved layout
     */
    private function buildResolvedLayout(array $layout): array
    {
        $resolved = $layout;
        $resolved[ConfigKeys::SECTIONS->value] = [];

        $sections = $layout[ConfigKeys::SECTIONS->value] ?? [];

        foreach ($sections as $section) {
            $resolved[ConfigKeys::SECTIONS->value][] = $this->buildResolvedSection($section);
        }

        return $resolved;
    }

    /**
     * Vytvoří resolved sekci layoutu.
     * Resolve všech block definic uvnitř sekce.
     *
     * @param array $section Raw sekce
     *
     * @return array Resolved sekce
     */
    private function buildResolvedSection(array $section): array
    {
        $resolved = $section;
        $resolved[ConfigKeys::BLOCKS->value] = [];

        $blocks = $section[ConfigKeys::BLOCKS->value] ?? [];

        foreach ($blocks as $blockDef) {
            $resolved[ConfigKeys::BLOCKS->value][] = $this->resolveBlockDefinition($blockDef);
        }

        return $resolved;
    }

    /**
     * Vytvoří resolved definici blocku.
     *
     * Pokud obsahuje `ref`, reference je expandována
     * a sloučena s lokální definicí.
     *
     * @param array $blockDef Raw definice blocku
     *
     * @return array Resolved definice blocku
     */
    private function resolveBlockDefinition(array $blockDef): array
    {
        $ref = $blockDef[ConfigKeys::REF->value] ?? null;

        if ($ref === null) {
            return $blockDef;
        }

        return $this->resolveBlockReference($ref, $blockDef);
    }
}