<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro vytváření checkbox input polí.
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['checkbox'])]
class CheckboxHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro checkbox inputy */
	private const ALLOWED_ATTRIBUTES = [];

    /**
     * Vytvoří checkbox input pole na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným checkbox polem
     */
    public function create(array $block): array
    {
        $baseAttributes = [
            'required'      => parent::isRequired($block),
            'disabled'      => parent::getRule($block, 'disabled', false),
            'type:checkbox' => $this->resolveTypeAttributes($block),
        ];

        $metaAttributes = parent::prepareMetaAttributes($block);

        $field = Form::Input(
            $block['id'],
            $block['name'],
            array_merge($baseAttributes, $metaAttributes)
        );

        parent::applyDefaults($field, $block);

        return [$field];
    }

    /**
     * Sestaví atributy specifické pro checkbox inputy.
     *
     * @param array $block Konfigurační blok
     * @return array
     */
    private function resolveTypeAttributes(array $block): array
    {
		return array_filter(
			[
				...array_combine(
					self::ALLOWED_ATTRIBUTES,
					array_map(
						fn($key) => $this->getRule($block, $key),
						self::ALLOWED_ATTRIBUTES
					)
				),
				'value'   => $block['value'] ?? '1',
				'checked' => parent::defaultValue('checkbox', $block),
			],
			fn($value) => $value !== null
		);
    }
}
