<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro skryté input pole (hidden).
 *
 * Renderuje minimální <input type="hidden"> bez labelu a doplňkového textu.
 * Hodnota se typicky nastavuje přes inject context při renderování formuláře.
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['hidden'])]
class HiddenHandler extends FieldConfigurator
{
    /**
     * Vytvoří skryté pole na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným hidden polem
     */
    public function create(array $block): array
    {
        $baseAttributes = [
            'type:hidden' => [
                'value' => $block['value'] ?? '',
            ],
        ];

        $metaAttributes = parent::prepareMetaAttributes($block);

        $field = Form::Input(
            $block['id'],
            $block['name'],
            array_merge($baseAttributes, $metaAttributes)
        );

        // Hidden inputy nemají label ani small text

        return [$field];
    }
}
