<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro vytváření skupiny radio input polí.
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['radio'])]
class RadioHandler extends FieldConfigurator
{
    /**
     * Vytvoří skupinu radio input polí na základě zadaného bloku.
     *
     * Všechny radio elementy sdílejí jeden layout wrapper s data-block-id
     * odpovídajícím ID bloku (např. "topic_source"). Block ID je nastaveno
     * v FormRenderer před zavoláním createFromBlock().
     *
     * @param array $block Konfigurační blok
     * @return array Pole vytvořených radio input polí
     */
    public function create(array $block): array
    {
        $fields = [];

        // Pro každý prvek ve skupině vytvoří samostatné radio pole.
        // Všechny sdílejí společný block ID (nastavený FormRenderer),
        // takže budou seskupeny do jednoho layout wrapperu.
        foreach ($block['group'] as $option) {
            // Sestaví unikátní ID pro každé radio pole.
            $id = "{$block['id']}_{$option['value']}";

            $baseAttributes = [
                'required'   => parent::isRequired($block),
                'disabled'   => parent::getRule($block, 'disabled', false),
                'type:radio' => $this->resolveTypeAttributes($block, $option),
            ];

            $metaAttributes = parent::prepareMetaAttributes($block);

            $field = Form::Input(
                $block['name'],
                $id,
                array_merge($baseAttributes, $metaAttributes)
            );

            $field->setLabel(['text' => $option['label']]);

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Sestaví atributy specifické pro radio inputy.
     *
     * @param array $block Konfigurační blok
     * @param array $option Aktuální option z group
     * @return array
     */
    public function resolveTypeAttributes(array $block, array $option): array
    {
        return [
            'value'   => $option['value'],
            'checked' => parent::defaultValue('radio', $block, $option['value']),
        ];
    }
}
