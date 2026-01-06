<?php

namespace Handler\Fields;
use FieldFactory\Form;

/**
 * Handler pro vytváření skupiny radio input polí.
 */
class RadioHandler extends FieldConfigurator
{
	/** @var array Podporované varianty tohoto handleru */
	private const SUPPORTED_VARIANTS = ['radio'];

	/** @var array Povolené atributy specifické pro type:radio (z rules) */
	private const ALLOWED_ATTRIBUTES = [];  // Radio nemá žádné rules-based atributy v type:radio

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
     * Vytvoří skupinu radio input polí na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole vytvořených radio input polí
     */
    public function create(array $block): array
    {
        $fields = [];

		// Pro každý prvek ve skupině vytvoří samostatné radio pole.
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
