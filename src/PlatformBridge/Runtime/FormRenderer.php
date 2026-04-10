<?php

declare(strict_types=1);

namespace PlatformBridge\Runtime;

use PlatformBridge\Config\ConfigManager;
use PlatformBridge\Config\ConfigKeys;
use PlatformBridge\Handler\FieldFactory;
use PlatformBridge\Form\Form;
use PlatformBridge\Template\Engine;

/**
 * Vykreslování formulářů podle konfigurace.
 *
 * Sestaví HTML sekce formuláře na základě JSON konfigurace (generátory, layouty, blocks).
 *
 * Best practice:
 *  - Kontext (dynamické hodnoty) předávejte přes klíče odpovídající 'name' nebo 'id' bloku
 *  - Pro každý render volat build() s novým kontextem
 *
 * @see PlatformBridge\Config\ConfigManager
 */
final class FormRenderer
{
    /**
     * @param FieldFactory $fieldFactory Factory pro tvorbu polí formuláře
     * @param ConfigManager $config Manažer konfigurace
     * @param Engine $engine Template engine pro renderování
     */
    public function __construct(
        private FieldFactory $fieldFactory,
        private ConfigManager $config,
        private Engine $engine
    ) {
    }

    /**
     * Sestaví pole sekcí formuláře na základě ID generátoru.
     *
     * @param string $generatorId ID generátoru (klíč v generators.json)
     * @param array<string, mixed> $context Dynamické hodnoty pro injekci do bloků formuláře.
     *        Klíče odpovídají atributu 'name' nebo 'id' bloku, hodnoty se nastaví jako 'value'.
     *
     * @return array[] Pole sekcí s HTML kódem a metadaty (id, label, columns, ...)
     *
     * @example
     *   $sections = $renderer->build('subject', ['client_id' => 633]);
     */
    public function build(string $generatorId, array $context = []): array
    {
        $generator = $this->config->getGenerator($generatorId);
        $layoutRef = $generator[ConfigKeys::LAYOUT_REF->value];

        $sections = [];

        foreach ($this->config->getResolvedSections($layoutRef) as $section) {
            $sectionId = $section[ConfigKeys::ID->value];

            $rawBlockDefs = $this->config->getSectionBlocks($layoutRef, $sectionId);

            foreach ($rawBlockDefs as $block) {
                $block = $this->applyContext($block, $context);

                Form::setCurrentBlock($block[ConfigKeys::ID->value] ?? null);
                // Všechny elementy bloku (i radio skupina) sdílejí
                // jeden wrapper s tímto data-block-id.
                $this->fieldFactory->createFromBlock($block);
            }

            $sections[] = [
                ConfigKeys::ID->value => $sectionId,
                ConfigKeys::LABEL->value => $section[ConfigKeys::LABEL->value] ?? null,
                ConfigKeys::COLUMN_TEMPLATE->value => $section[ConfigKeys::COLUMN_TEMPLATE->value] ?? null,
                ConfigKeys::COLUMNS->value => $section[ConfigKeys::COLUMNS->value] ?? null,
                'html' => Form::renderWrapped($this->engine, $rawBlockDefs),
            ];
        }

        return $sections;
    }

    /**
     * Aplikuje hodnoty z kontextu podle name/id bloku.
     *
     * @param array $block Konfigurační blok
     * @param array<string, mixed> $context Dynamické hodnoty [název_pole => hodnota]
     *
     * @return array Blok s případně nastavenou hodnotou
     */
    private function applyContext(array $block, array $context): array
    {
        if (empty($context)) {
            return $block;
        }

        $name = $block[ConfigKeys::NAME->value] ?? null;
        $id = $block[ConfigKeys::ID->value] ?? null;

        // Priorita: shoda podle name, pak podle id
        if ($name !== null && array_key_exists($name, $context)) {
            $block['value'] = $context[$name];
        } elseif ($id !== null && array_key_exists($id, $context)) {
            $block['value'] = $context[$id];
        }

        return $block;
    }
}
