<?php

declare(strict_types=1);

namespace PlatformBridge\Config;

use PlatformBridge\Shared\Exception\ConfigException;

/**
 * Validátor konfiguračních JSON souborů PlatformBridge.
 *
 * Poskytuje:
 * - Validaci jednotlivých kolekcí (blocks, layouts, generators)
 * - Cross-validaci vztahů mezi entitami (ref → block, layout_ref → layout)
 * - Schéma-like kontrolu typů, rozsahů a povinných klíčů
 */
final class ConfigValidator
{
    private const GRID_MIN = 1;
    private const GRID_MAX = 12;
    private const ALLOWED_INPUT_VARIANTS = ['hidden', 'text', 'password', 'email', 'number', 'url', 'radio', 'checkbox'];

    // =========================================================================
    // Veřejné API – validace kolekcí
    // =========================================================================

    /**
     * Validuje kolekci generátorů.
     *
     * @return array Validované generátory (hodnota klíče "generators")
     * @throws ConfigException
     */
    public function validateGenerators(array $data, string $filename = 'generators.json'): array
    {
        return $this->validateCollection(
            $data,
            ConfigKeys::GENERATORS->value,
            $filename,
            fn(array $item, string $key) => $this->validateGenerator($item, $key, $filename)
        );
    }

    /**
     * Validuje kolekci layoutů.
     *
     * @return array Validované layouty (hodnota klíče "layouts")
     * @throws ConfigException
     */
    public function validateLayouts(array $data, string $filename = 'layouts.json'): array
    {
        return $this->validateCollection(
            $data,
            ConfigKeys::LAYOUTS->value,
            $filename,
            fn(array $item, string $key) => $this->validateLayout($item, $key, $filename)
        );
    }

    /**
     * Validuje kolekci bloků.
     *
     * @return array Validované bloky (hodnota klíče "blocks")
     * @throws ConfigException
     */
    public function validateBlocks(array $data, string $filename = 'blocks.json'): array
    {
        return $this->validateCollection(
            $data,
            ConfigKeys::BLOCKS->value,
            $filename,
            fn(array $item, string $key) => $this->validateBlock($item, $key, $filename)
        );
    }

    // =========================================================================
    // Veřejné API – cross-validace vztahů
    // =========================================================================

    /**
     * Kontroluje referenční integritu mezi konfiguračními soubory.
     *
     * Ověřuje:
     * - Každý generator.layout_ref odkazuje na existující layout
     * - Každý block ref v layout sekcích odkazuje na existující block
     *
     * @param array $blocks     Validované bloky (klíč → definice)
     * @param array $layouts    Validované layouty (klíč → definice)
     * @param array $generators Validované generátory (klíč → definice)
     *
     * @throws ConfigException Pokud reference odkazuje na neexistující entitu
     */
    public function validateRelations(array $blocks, array $layouts, array $generators): void
    {
        $blockIds = array_keys($blocks);
        $layoutIds = array_keys($layouts);

        // Generátor → layout_ref musí existovat v layouts
        foreach ($generators as $generatorKey => $generator) {
            $layoutRef = $generator[ConfigKeys::LAYOUT_REF->value] ?? null;

            if ($layoutRef !== null && !in_array($layoutRef, $layoutIds, true)) {
                throw ConfigException::invalidReference(
                    'layout',
                    $layoutRef,
                    "generator \"{$generatorKey}\" odkazuje na neexistující layout"
                );
            }
        }

        // Layout sekce → block ref musí existovat v blocks
        foreach ($layouts as $layoutKey => $layout) {
            $sections = $layout[ConfigKeys::SECTIONS->value] ?? [];

            foreach ($sections as $sectionIndex => $section) {
                $sectionId = $section[ConfigKeys::ID->value] ?? "#{$sectionIndex}";
                $sectionBlocks = $section[ConfigKeys::BLOCKS->value] ?? [];

                foreach ($sectionBlocks as $blockIndex => $blockRef) {
                    $ref = $blockRef[ConfigKeys::REF->value] ?? null;

                    if ($ref !== null && !in_array($ref, $blockIds, true)) {
                        throw ConfigException::invalidReference(
                            'block',
                            $ref,
                            "layout \"{$layoutKey}\", sekce \"{$sectionId}\", blok #{$blockIndex}"
                        );
                    }
                }
            }
        }
    }

    // =========================================================================
    // Interní – validace kolekce
    // =========================================================================

    private function validateCollection(array $data, string $key, string $filename, callable $validator): array
    {
        $items = $this->requireArray($data, $key, $filename);

        foreach ($items as $itemKey => $item) {
            if (!is_array($item)) {
                throw ConfigException::invalidStructure($filename, "Položka \"{$itemKey}\" musí být objekt.");
            }

            $validator($item, $itemKey);
        }

        return $items;
    }

    // =========================================================================
    // Interní – validace generátoru
    // =========================================================================

    private function validateGenerator(array $generator, string $key, string $filename): void
    {
        $this->requireKeys($generator, [
            ConfigKeys::ID->value,
            ConfigKeys::LABEL->value,
            ConfigKeys::LAYOUT_REF->value,
        ], $filename, "generator \"{$key}\"");

        $this->assertString($generator[ConfigKeys::ID->value], "generator {$key}.id");
        $this->assertString($generator[ConfigKeys::LABEL->value], "generator {$key}.label");
        $this->assertString($generator[ConfigKeys::LAYOUT_REF->value], "generator {$key}.layout_ref");
    }

    // =========================================================================
    // Interní – validace layoutu a jeho sekcí
    // =========================================================================

    private function validateLayout(array $layout, string $layoutId, string $filename): void
    {
        $sections = $this->requireArray($layout, ConfigKeys::SECTIONS->value, $filename, "layout \"{$layoutId}\"");
        $this->assertNotEmpty($sections, "layout {$layoutId}.sections");

        if (isset($layout[ConfigKeys::COLUMNS->value])) {
            $this->assertIntRange($layout[ConfigKeys::COLUMNS->value], self::GRID_MIN, self::GRID_MAX, "layout {$layoutId}.columns");
        }

        foreach ($sections as $index => $section) {
            $this->validateSection($section, $layoutId, $index, $filename);
        }
    }

    private function validateSection(array $section, string $layoutId, int $index, string $filename): void
    {
        $context = "section #{$index} in {$layoutId}";

        $this->requireKeys($section, [
            ConfigKeys::ID->value,
            ConfigKeys::BLOCKS->value,
        ], $filename, $context);

        $this->assertString($section[ConfigKeys::ID->value], "{$context}.id");

        $blocks = $this->requireArray($section, ConfigKeys::BLOCKS->value, $filename, $context);
        $this->assertNotEmpty($blocks, "{$context}.blocks");

        if (isset($section[ConfigKeys::COLUMNS->value])) {
            $this->assertIntRange($section[ConfigKeys::COLUMNS->value], self::GRID_MIN, self::GRID_MAX, "{$context}.columns");
        }

        if (isset($section[ConfigKeys::COLUMN_TEMPLATE->value])) {
            $this->assertString($section[ConfigKeys::COLUMN_TEMPLATE->value], "{$context}.column_template");
        }

        foreach ($blocks as $i => $block) {
            $this->validateBlockRef($block, $layoutId, $section[ConfigKeys::ID->value], $i, $filename);
        }
    }

    private function validateBlockRef(array $block, string $layoutId, string $sectionId, int $index, string $filename): void
    {
        $context = "block #{$index} in {$layoutId}/{$sectionId}";

        $this->requireKeys($block, [ConfigKeys::REF->value], $filename, $context);
        $this->assertString($block[ConfigKeys::REF->value], "{$context}.ref");

        foreach ([ConfigKeys::SPAN->value, ConfigKeys::ROW_SPAN->value] as $key) {
            if (isset($block[$key])) {
                $this->assertIntRange($block[$key], self::GRID_MIN, self::GRID_MAX, "{$context}.{$key}");
            }
        }

        foreach ([ConfigKeys::GRID_COLUMN->value, ConfigKeys::GRID_ROW->value] as $key) {
            if (isset($block[$key])) {
                $this->assertString($block[$key], "{$context}.{$key}");
            }
        }
    }

    // =========================================================================
    // Interní – validace bloků (form elementy)
    // =========================================================================

    private function validateBlock(array $block, string $key, string $filename): void
    {
        $this->requireKeys($block, [
            ConfigKeys::ID->value,
            ConfigKeys::NAME->value,
            ConfigKeys::COMPONENT->value,
        ], $filename, "block \"{$key}\"");

        foreach ([ConfigKeys::ID->value, ConfigKeys::NAME->value, ConfigKeys::COMPONENT->value] as $field) {
            $this->assertString($block[$field], "block {$key}.{$field}");
        }

        match ($block[ConfigKeys::COMPONENT->value]) {
            'input'  => $this->validateInput($block, $key, $filename),
            'select' => $this->validateSelect($block, $key, $filename),
            default  => null,
        };
    }

    private function validateInput(array $block, string $key, string $filename): void
    {
        $variant = $block[ConfigKeys::VARIANT->value] ?? null;

        if ($variant !== null && !in_array($variant, self::ALLOWED_INPUT_VARIANTS, true)) {
            throw ConfigException::validationFailed("Neplatná varianta inputu \"{$variant}\" v bloku \"{$key}\"");
        }

        if ($variant === 'radio') {
            $group = $this->requireArray($block, ConfigKeys::GROUP->value, $filename, "block \"{$key}\"");
            $this->validateOptions($group, $key, 'group', $filename);
        }
    }

    private function validateSelect(array $block, string $key, string $filename): void
    {
        $options = $this->requireArray($block, ConfigKeys::OPTIONS->value, $filename, "block \"{$key}\"");
        $this->validateOptions($options, $key, 'options', $filename);
    }

    private function validateOptions(array $list, string $blockKey, string $name, string $filename): void
    {
        foreach ($list as $i => $opt) {
            if (!is_array($opt)) {
                throw ConfigException::invalidStructure($filename, "{$name}[{$i}] v bloku \"{$blockKey}\" musí být objekt");
            }

            foreach (['value', 'label'] as $field) {
                $this->assertString($opt[$field] ?? null, "{$name}[{$i}].{$field}");
            }
        }
    }

    // =========================================================================
    // Interní – asserty a pomocné metody
    // =========================================================================

    private function requireArray(array $data, string $key, string $filename, string $context = ''): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $message = "Chybí pole \"{$key}\"";
            if ($context !== '') {
                $message .= " v kontextu: {$context}";
            }
            throw ConfigException::invalidStructure($filename, $message);
        }

        return $data[$key];
    }

    private function requireKeys(array $data, array $keys, string $filename, string $context): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw ConfigException::missingKey($filename, $key, $context);
            }
        }
    }

    private function assertString(mixed $value, string $context): void
    {
        if (!is_string($value) || $value === '') {
            throw ConfigException::validationFailed("Neplatný řetězec: {$context}");
        }
    }

    private function assertIntRange(mixed $value, int $min, int $max, string $context): void
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw ConfigException::validationFailed("{$context} musí být int v rozsahu {$min}–{$max}");
        }
    }

    private function assertNotEmpty(array $arr, string $context): void
    {
        if ($arr === []) {
            throw ConfigException::validationFailed("{$context} nesmí být prázdné");
        }
    }
}
