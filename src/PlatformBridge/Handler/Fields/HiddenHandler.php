<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;

/**
 * Handler pro skryté input pole (hidden).
 *
 * Renderuje minimální <input type="hidden"> bez labelu a doplňkového textu.
 * Hodnota se typicky nastavuje přes inject context při renderování formuláře.
 */
class HiddenHandler extends FieldConfigurator
{
    /** @var array Podporované varianty tohoto handleru */
    private const SUPPORTED_VARIANTS = ['hidden'];

    /**
     * Určuje, zda tento handler podporuje zadaný blok.
     *
     * @param array $block Konfigurační blok
     * @return bool
     */
    public function supports(array $block): bool
    {
        return ($block['component'] ?? null) === 'input'
            && in_array($block['variant'] ?? '', self::SUPPORTED_VARIANTS, true);
    }

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
