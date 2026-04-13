<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro číselné input pole (number, range).
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['number', 'range'])]
class NumberHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro číselné inputy */
	private const ALLOWED_ATTRIBUTES = ['min', 'max', 'step'];

    /**
     * Vytvoří pole typu input na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným input polem
     */
    public function create(array $block): array
    {
        $variant = $block['variant'] ?? 'number';

		$baseAttributes = [
			// "value"           => $block["value"] ?? null,
			"required"        => parent::isRequired($block),
			"readonly"        => parent::getRule($block, "readonly", false),
			"disabled"        => parent::getRule($block, "disabled", false),
			"placeholder"     => parent::getRule($block, "placeholder", ""),
			"type:{$variant}" => $this->resolveTypeAttributes($block),
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
     * Sestaví atributy specifické pro číselné inputy.
     *
     * @param array $block Konfigurační blok
     * @return array
     */
	public function resolveTypeAttributes(array $block): array
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
				'value' => $block['value'] ?? "",
			],
			fn($value) => $value !== null
		);
	}
}
