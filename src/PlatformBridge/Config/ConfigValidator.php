<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config;

use Zoom\PlatformBridge\Config\Exception\ConfigException;

/**
 * Validátor konfiguračních souborů PlatformBridge.
 *
 * Provádí strukturální validaci JSON konfigurace pro:
 * - blocks.json - definice formulářových bloků
 * - layouts.json - rozložení sekcí a bloků
 * - generators.json - generátory AI obsahu
 */
final class ConfigValidator
{
    /**
     * @param bool $strictMode Pokud true, vyhodí výjimku i pro varování
     */
    public function __construct(){}

    /**
     * Validuje konfiguraci generátorů.
     *
     * @param array $data Obsah generators.json
     * @param string $filename Název souboru pro chybové hlášky
     * @return array<string, array> Validované generátory
	 *
     * @throws ConfigException
     */
    public function validateGenerators(array $data, string $filename = 'generators.json'): array
    {
        $key = ConfigKeys::GENERATORS->value;

        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw ConfigException::invalidStructure(
                $filename,
                "Musí obsahovat objekt \"{$key}\"."
            );
        }

        $generators = $data[$key];

        foreach ($generators as $genKey => $generator) {
            $this->validateGenerator($generator, $genKey, $filename);
        }

        return $generators;
    }

    /**
     * Validuje konfiguraci layoutů.
     *
     * @param array $data Obsah layouts.json
     * @param string $filename Název souboru pro chybové hlášky
     * @return array<string, array> Validované layouty
	 *
     * @throws ConfigException
     */
    public function validateLayouts(array $data, string $filename = 'layouts.json'): array
    {
        $key = ConfigKeys::LAYOUTS->value;

        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw ConfigException::invalidStructure(
                $filename,
                "Musí obsahovat objekt \"{$key}\"."
            );
        }

        $layouts = $data[$key];

        foreach ($layouts as $layoutId => $layout) {
            $this->validateLayout($layout, $layoutId, $filename);
        }

        return $layouts;
    }

    /**
     * Validuje konfiguraci bloků.
     *
     * @param array $data Obsah blocks.json
     * @param string $filename Název souboru pro chybové hlášky
     * @return array<string, array> Validované bloky
	 *
     * @throws ConfigException
     */
    public function validateBlocks(array $data, string $filename = 'blocks.json'): array
    {
        $key = ConfigKeys::BLOCKS->value;

        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw ConfigException::invalidStructure(
                $filename,
                "Musí obsahovat objekt \"{$key}\"."
            );
        }

        $blocks = $data[$key];

        foreach ($blocks as $blockKey => $block) {
            $this->validateBlock($block, $blockKey, $filename);
        }

        return $blocks;
    }

    /**
     * Cross-validace vztahů mezi blocks, layouts a generators.
     *
     * @param array<string, array> $blocks
     * @param array<string, array> $layouts
     * @param array<string, array> $generators
	 *
     * @throws ConfigException
     */
    public function validateRelations(array $blocks, array $layouts, array $generators): void
    {
        // 1) Generátory → layouty
        foreach ($generators as $genKey => $generator) {
            $layoutRef = $generator[ConfigKeys::LAYOUT_REF->value] ?? null;

            if (!is_string($layoutRef) || $layoutRef === '') {
                throw ConfigException::invalidReference(
                    'layout',
                    (string)$layoutRef,
                    "Generátor \"{$genKey}\" má neplatný layout_ref"
                );
            }

            if (!isset($layouts[$layoutRef])) {
                throw ConfigException::invalidReference(
                    'layout',
                    $layoutRef,
                    "Generátor \"{$genKey}\" odkazuje na neexistující layout"
                );
            }
        }

        // 2) Layouty → bloky
        foreach ($layouts as $layoutId => $layout) {
            $sections = $layout[ConfigKeys::SECTIONS->value] ?? [];

            foreach ($sections as $sectionIndex => $section) {
                $sectionId = $section[ConfigKeys::ID->value] ?? "#{$sectionIndex}";
                $blocksInSection = $section[ConfigKeys::BLOCKS->value] ?? [];

                foreach ($blocksInSection as $blockIndex => $blockDef) {
                    if (!is_array($blockDef)) {
                        continue;
                    }

                    $ref = $blockDef[ConfigKeys::REF->value] ?? null;

                    if ($ref !== null && !isset($blocks[$ref])) {
                        throw ConfigException::invalidReference(
                            'block',
                            $ref,
                            "Layout \"{$layoutId}\", sekce \"{$sectionId}\""
                        );
                    }
                }
            }
        }
    }

    /**
     * Validuje jeden generátor v konfiguraci generators.json.
     *
     * @param mixed $generator Generátor (pole s klíči id, label, layout_ref)
     * @param string $genKey Klíč generátoru v konfiguraci (např. 'text', 'image')
     * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud je struktura nebo hodnoty neplatné
     */
    private function validateGenerator(mixed $generator, string $genKey, string $filename): void
    {
        if (!is_array($generator)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Generátor \"{$genKey}\" musí být objekt."
            );
        }

        $requiredKeys = [
            ConfigKeys::ID->value,
            ConfigKeys::LABEL->value,
            ConfigKeys::LAYOUT_REF->value
        ];

        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $generator)) {
                throw ConfigException::missingKey($filename, $requiredKey, "generátor \"{$genKey}\"");
            }
        }

        if (!is_string($generator[ConfigKeys::ID->value]) || $generator[ConfigKeys::ID->value] === '') {
            throw ConfigException::validationFailed(
                "Generátor \"{$genKey}\" má neplatné \"id\"."
            );
        }
    }

    /**
     * Validuje jeden layout v konfiguraci layouts.json.
     *
     * @param mixed $layout Layout (pole s klíči sections, columns, ...)
     * @param string $layoutId Identifikátor layoutu v konfiguraci
     * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud je struktura nebo hodnoty neplatné
     */
    private function validateLayout(mixed $layout, string $layoutId, string $filename): void
    {
        if (!is_array($layout)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Layout \"{$layoutId}\" musí být objekt."
            );
        }

        $sectionsKey = ConfigKeys::SECTIONS->value;

        if (!isset($layout[$sectionsKey]) || !is_array($layout[$sectionsKey]) || $layout[$sectionsKey] === []) {
            throw ConfigException::invalidStructure(
                $filename,
                "Layout \"{$layoutId}\" musí obsahovat neprázdné pole \"sections\"."
            );
        }

        // Validace volitelného columns na úrovni layoutu
        if (array_key_exists(ConfigKeys::COLUMNS->value, $layout)) {
            $this->validateColumnsValue($layout[ConfigKeys::COLUMNS->value], "layout \"{$layoutId}\"", $filename);
        }

        foreach ($layout[$sectionsKey] as $sectionIndex => $section) {
            $this->validateSection($section, $sectionIndex, $layoutId, $filename);
        }
    }

    /**
     * Validuje jednu sekci v layoutu (pole sekcí v layouts.json).
     *
     * @param mixed $section Sekce (pole s klíči id, blocks, columns, column_template, ...)
     * @param int $sectionIndex Index sekce v layoutu (pořadí v poli)
     * @param string $layoutId Identifikátor layoutu, ve kterém je sekce
     * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud je struktura nebo hodnoty neplatné
     */
    private function validateSection(mixed $section, int $sectionIndex, string $layoutId, string $filename): void
    {
        if (!is_array($section)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Sekce #{$sectionIndex} v layoutu \"{$layoutId}\" musí být objekt."
            );
        }

        $idKey = ConfigKeys::ID->value;
        $blocksKey = ConfigKeys::BLOCKS->value;

        if (!isset($section[$idKey]) || !is_string($section[$idKey]) || $section[$idKey] === '') {
            throw ConfigException::invalidStructure(
                $filename,
                "Sekce #{$sectionIndex} v layoutu \"{$layoutId}\" musí mít neprázdné \"id\"."
            );
        }

        if (!isset($section[$blocksKey]) || !is_array($section[$blocksKey]) || $section[$blocksKey] === []) {
            throw ConfigException::invalidStructure(
                $filename,
                "Sekce \"{$section[$idKey]}\" v layoutu \"{$layoutId}\" musí obsahovat neprázdné pole \"blocks\"."
            );
        }

        // Validace volitelného columns na úrovni sekce
        if (array_key_exists(ConfigKeys::COLUMNS->value, $section)) {
            $this->validateColumnsValue(
                $section[ConfigKeys::COLUMNS->value],
                "sekce \"{$section[$idKey]}\" v layoutu \"{$layoutId}\"",
                $filename
            );
        }

        // Validace volitelného column_template na úrovni sekce
        if (array_key_exists(ConfigKeys::COLUMN_TEMPLATE->value, $section)) {
            $this->validateColumnTemplateValue(
                $section[ConfigKeys::COLUMN_TEMPLATE->value],
                "sekce \"{$section[$idKey]}\" v layoutu \"{$layoutId}\"",
                $filename
            );
        }

        foreach ($section[$blocksKey] as $blockIndex => $blockDef) {
            $this->validateBlockReference($blockDef, $blockIndex, $section[$idKey], $layoutId, $filename);
        }
    }

    /**
     * Validuje jeden blok v sekci layoutu (reference na blok v blocks.json).
     *
     * @param mixed $blockDef Definice bloku v sekci (pole s klíči ref, span, row_span, grid_column, grid_row, ...)
     * @param int $blockIndex Index bloku v sekci (pořadí v poli)
     * @param string $sectionId Identifikátor sekce, ve které je blok
     * @param string $layoutId Identifikátor layoutu, ve kterém je sekce
     * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud je struktura nebo hodnoty neplatné
     */
    private function validateBlockReference(mixed $blockDef, int $blockIndex, string $sectionId, string $layoutId, string $filename): void
    {
        if (!is_array($blockDef)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Blok #{$blockIndex} v sekci \"{$sectionId}\" layoutu \"{$layoutId}\" musí být objekt."
            );
        }

        $refKey = ConfigKeys::REF->value;

        if (!isset($blockDef[$refKey]) || !is_string($blockDef[$refKey]) || $blockDef[$refKey] === '') {
            throw ConfigException::invalidStructure(
                $filename,
                "Blok #{$blockIndex} v sekci \"{$sectionId}\" layoutu \"{$layoutId}\" musí obsahovat platný \"ref\"."
            );
        }

        // Validace volitelného span
        if (array_key_exists(ConfigKeys::SPAN->value, $blockDef)) {
            $this->validateSpanValue(
                $blockDef[ConfigKeys::SPAN->value],
                $blockDef[$refKey],
                $sectionId,
                $layoutId,
                $filename
            );
        }

        // Validace volitelného row_span
        if (array_key_exists(ConfigKeys::ROW_SPAN->value, $blockDef)) {
            $this->validateSpanValue(
                $blockDef[ConfigKeys::ROW_SPAN->value],
                $blockDef[$refKey],
                $sectionId,
                $layoutId,
                $filename
            );
        }

        // Validace volitelného grid_column (string, např. "1 / -1")
        if (array_key_exists(ConfigKeys::GRID_COLUMN->value, $blockDef)) {
            $this->validateGridPositionValue(
                $blockDef[ConfigKeys::GRID_COLUMN->value],
                'grid_column',
                $blockDef[$refKey],
                $sectionId,
                $layoutId,
                $filename
            );
        }

        // Validace volitelného grid_row (string, např. "1 / -1")
        if (array_key_exists(ConfigKeys::GRID_ROW->value, $blockDef)) {
            $this->validateGridPositionValue(
                $blockDef[ConfigKeys::GRID_ROW->value],
                'grid_row',
                $blockDef[$refKey],
                $sectionId,
                $layoutId,
                $filename
            );
        }
    }

    /**
     * Validuje hodnotu columns (počet sloupců) v layoutu nebo sekci.
     *
     * @param mixed $value Hodnota columns (očekává se celé číslo 1–12)
     * @param string $context Kontext pro chybovou hlášku (např. "layout 'main'", "sekce 'A'")

	* @throws ConfigException Pokud columns není platné číslo
     */
    private function validateColumnsValue(mixed $value, string $context): void
    {
        if (!is_int($value) || $value < 1 || $value > 12) {
            throw ConfigException::validationFailed(
                "Hodnota \"columns\" v {$context} musí být celé číslo od 1 do 12."
            );
        }
    }

    /**
     * Validuje hodnotu span (počet sloupců/bloků) pro konkrétní blok v sekci layoutu.
     *
     * @param mixed $value Hodnota span (očekává se celé číslo 1–12)
     * @param string $ref Identifikátor bloku (ref)
     * @param string $sectionId Identifikátor sekce, ve které je blok
     * @param string $layoutId Identifikátor layoutu, ve kterém je sekce

	* @throws ConfigException Pokud span není platné číslo
     */
    private function validateSpanValue(mixed $value, string $ref, string $sectionId, string $layoutId): void
    {
        if (!is_int($value) || $value < 1 || $value > 12) {
            throw ConfigException::validationFailed(
                "Hodnota \"span\" pro blok \"{$ref}\" v sekci \"{$sectionId}\" layoutu \"{$layoutId}\" musí být celé číslo od 1 do 12."
            );
        }
    }

    /**
     * Validuje hodnotu grid pozice (např. grid_column, grid_row) pro blok v sekci layoutu.
     *
     * @param mixed $value Hodnota grid pozice (očekává se neprázdný string, např. "1 / -1", "full")
     * @param string $property Název vlastnosti (grid_column nebo grid_row)
     * @param string $ref Identifikátor bloku (ref)
     * @param string $sectionId Identifikátor sekce, ve které je blok
     * @param string $layoutId Identifikátor layoutu, ve kterém je sekce

	* @throws ConfigException Pokud grid pozice není platný string
     */
    private function validateGridPositionValue(mixed $value, string $property, string $ref, string $sectionId, string $layoutId): void
    {
        if (!is_string($value) || $value === '') {
            throw ConfigException::validationFailed(
                "Hodnota \"{$property}\" pro blok \"{$ref}\" v sekci \"{$sectionId}\" layoutu \"{$layoutId}\" musí být neprázdný řetězec (např. \"1 / -1\", \"full\")."
            );
        }
    }

    /**
     * Validuje hodnotu column_template (šablona sloupců) pro layout nebo sekci.
     *
     * @param mixed $value Hodnota column_template (očekává se neprázdný string, např. "auto auto 1fr")
     * @param string $context Kontext pro chybovou hlášku (např. "layout 'main'", "sekce 'A'")

	 * @throws ConfigException Pokud column_template není platný string
     */
    private function validateColumnTemplateValue(mixed $value, string $context): void
    {
        if (!is_string($value) || $value === '') {
            throw ConfigException::validationFailed(
                "Hodnota \"column_template\" v {$context} musí být neprázdný řetězec (např. \"auto auto 1fr\")."
            );
        }
    }

	/**
	 * Validuje jeden blok v konfiguraci blocks.json.
	 *
	 * @param mixed $block Definice bloku (pole s klíči id, name, component, variant, rules, meta, ...)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'input', 'select', ...)
	 * @param string $filename Název souboru pro chybové hlášky

	 * @throws ConfigException Pokud je struktura nebo hodnoty bloku neplatné
	 */
    private function validateBlock(mixed $block, string $blockKey, string $filename): void
    {
        if (!is_array($block)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Block \"{$blockKey}\" musí být objekt."
            );
        }

        // Povinné klíče
        $requiredKeys = [
            ConfigKeys::ID->value,
            ConfigKeys::NAME->value,
            ConfigKeys::COMPONENT->value
        ];

        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $block)) {
                throw ConfigException::missingKey($filename, $requiredKey, "block \"{$blockKey}\"");
            }

            if (!is_string($block[$requiredKey]) || $block[$requiredKey] === '') {
                throw ConfigException::validationFailed(
                    "Block \"{$blockKey}\" má neplatné \"{$requiredKey}\"."
                );
            }
        }

        // Validace rules
        if (array_key_exists(ConfigKeys::RULES->value, $block)) {
            if (!is_array($block[ConfigKeys::RULES->value])) {
                throw ConfigException::invalidStructure(
                    $filename,
                    "Block \"{$blockKey}\" má klíč \"rules\" který musí být typu array."
                );
            }
        }

        // Validace meta atributů
        if (array_key_exists(ConfigKeys::META->value, $block)) {
            $this->validateMetaAttributes($block[ConfigKeys::META->value], $blockKey, $filename);
        }

        // Komponenta-specifická validace
        $component = $block[ConfigKeys::COMPONENT->value];
        $variant = $block[ConfigKeys::VARIANT->value] ?? null;

        $this->validateComponentSpecific($block, $blockKey, $component, $variant, $filename);
    }

	/**
	 * Provádí komponenta-specifickou validaci bloku podle typu komponenty a varianty.
	 *
	 * @param array $block Definice bloku (pole s klíči id, name, component, variant, rules, ...)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'input', 'select', ...)
	 * @param string $component Typ komponenty (např. 'input', 'select')
	 * @param string|null $variant Varianta komponenty (např. 'text', 'radio', 'checkbox'), nebo null
	 * @param string $filename Název souboru pro chybové hlášky

	 * @throws ConfigException Pokud je struktura nebo hodnoty bloku neplatné pro danou komponentu
	 */
    private function validateComponentSpecific(array $block, string $blockKey, string $component, ?string $variant, string $filename): void
    {
        $rules = $block[ConfigKeys::RULES->value] ?? [];

        if ($component === 'input' && $variant !== null) {
            $this->validateInputVariant($block, $blockKey, $variant, $rules, $filename);
        }

        if ($component === 'select') {
            $this->validateSelectBlock($block, $blockKey, $rules, $filename);
        }
    }

	/**
	 * Validuje variantu input komponenty (např. text, radio, checkbox) v bloku.
	 *
	 * @param array $block Definice bloku (pole s klíči id, name, component, variant, rules, group, ...)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'input')
	 * @param string $variant Varianta input komponenty (např. 'text', 'radio', 'checkbox')
	 * @param array $rules Pravidla validace (pole s klíči default, ...)
	 * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud je varianta neplatná nebo má neplatnou strukturu/hodnoty
	 */
    private function validateInputVariant(array $block, string $blockKey, string $variant, array $rules, string $filename): void
    {
        $allowedVariants = ['hidden', 'text', 'password', 'email', 'number', 'url', 'radio', 'checkbox'];

        if (!in_array($variant, $allowedVariants, true)) {
            throw ConfigException::validationFailed(
                "Block \"{$blockKey}\" má neznámý input variant \"{$variant}\"."
            );
        }

        if ($variant === 'radio') {
            $groupKey = ConfigKeys::GROUP->value;

            if (!isset($block[$groupKey]) || !is_array($block[$groupKey]) || $block[$groupKey] === []) {
                throw ConfigException::invalidStructure(
                    $filename,
                    "Block \"{$blockKey}\" (input + radio) musí obsahovat neprázdné \"group\"."
                );
            }

            $this->validateOptionList($block[$groupKey], $blockKey, 'group', $filename);
            $values = array_column($block[$groupKey], 'value');
            $this->validateDefaultInValues($rules, $values, $blockKey, 'input + radio');
        }

        if ($variant === 'checkbox') {
            $defaultKey = ConfigKeys::DEFAULT->value;
            if (array_key_exists($defaultKey, $rules) && !is_bool($rules[$defaultKey])) {
                throw ConfigException::validationFailed(
                    "Block \"{$blockKey}\" (input + checkbox) má v \"rules.default\" hodnotu, která není boolean."
                );
            }
        }
    }

	/**
	 * Validuje blok typu select (výběrový komponent) v konfiguraci.
	 *
	 * @param array $block Definice bloku (pole s klíči id, name, component, options, rules, ...)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'select')
	 * @param array $rules Pravidla validace (pole s klíči default, ...)
	 * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud blok nemá platné options nebo default hodnotu
	 */
    private function validateSelectBlock(array $block, string $blockKey, array $rules, string $filename): void
    {
        $optionsKey = ConfigKeys::OPTIONS->value;

        if (!isset($block[$optionsKey]) || !is_array($block[$optionsKey]) || $block[$optionsKey] === []) {
            throw ConfigException::invalidStructure(
                $filename,
                "Block \"{$blockKey}\" (component \"select\") musí obsahovat neprázdné \"options\"."
            );
        }

        $this->validateOptionList($block[$optionsKey], $blockKey, 'options', $filename);
        $values = array_column($block[$optionsKey], 'value');
        $this->validateDefaultInValues($rules, $values, $blockKey, 'select');
    }

	/**
	 * Validuje seznam možností (options/group) pro select nebo radio komponentu.
	 *
	 * @param array $list Seznam možností (pole objektů s klíči value, label)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'select', 'input')
	 * @param string $fieldName Název pole (např. 'options', 'group')
	 * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud některý prvek není objekt nebo nemá platné value/label
	 */
    private function validateOptionList(array $list, string $blockKey, string $fieldName, string $filename): void
    {
        foreach ($list as $index => $opt) {
            if (!is_array($opt)) {
                throw ConfigException::invalidStructure(
                    $filename,
                    "Prvek #{$index} v \"{$fieldName}\" blocku \"{$blockKey}\" musí být objekt."
                );
            }

            foreach (['value', 'label'] as $key) {
                if (!array_key_exists($key, $opt) || !is_string($opt[$key]) || $opt[$key] === '') {
                    throw ConfigException::validationFailed(
                        "Prvek #{$index} v \"{$fieldName}\" blocku \"{$blockKey}\" musí mít neprázdný \"{$key}\"."
                    );
                }
            }
        }
    }

	/**
	 * Validuje, zda je výchozí hodnota (default) povolená v seznamu hodnot pro select/radio.
	 *
	 * @param array $rules Pravidla validace (pole s klíčem default)
	 * @param array $values Seznam povolených hodnot (např. hodnoty options/group)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'select', 'input')
	 * @param string $context Kontext pro chybovou hlášku (např. 'select', 'input + radio')

	* @throws ConfigException Pokud default není v povolených hodnotách
	 */
    private function validateDefaultInValues(array $rules, array $values, string $blockKey, string $context): void
    {
        $defaultKey = ConfigKeys::DEFAULT->value;

        if (array_key_exists($defaultKey, $rules) && !in_array($rules[$defaultKey], $values, true)) {
            throw ConfigException::validationFailed(
                "Block \"{$blockKey}\" ({$context}) má \"rules.default\", který není v povolených hodnotách."
            );
        }
    }

	/**
	 * Validuje meta atributy bloku (pole meta).
	 *
	 * @param mixed $meta Meta atributy bloku (pole klíč/hodnota)
	 * @param string $blockKey Klíč bloku v konfiguraci (např. 'input', 'select', ...)
	 * @param string $filename Název souboru pro chybové hlášky

	* @throws ConfigException Pokud meta není pole nebo obsahuje neplatné klíče/hodnoty
	 */
    private function validateMetaAttributes(mixed $meta, string $blockKey, string $filename): void
    {
        if (!is_array($meta)) {
            throw ConfigException::invalidStructure(
                $filename,
                "Block \"{$blockKey}\" má klíč \"meta\", který musí být typu array."
            );
        }

        foreach ($meta as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw ConfigException::validationFailed(
                    "Block \"{$blockKey}\" má v \"meta\" neplatný klíč (musí být neprázdný string)."
                );
            }

            if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                throw ConfigException::validationFailed(
                    "Block \"{$blockKey}\" má v \"meta\" klíč \"{$key}\" s neplatnou hodnotou (povolené typy: string, int, float, bool)."
                );
            }
        }
    }
}