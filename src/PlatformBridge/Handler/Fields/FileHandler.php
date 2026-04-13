<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro souborové input pole (file).
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['file'])]
class FileHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro souborové inputy */
	private const ALLOWED_ATTRIBUTES = ['accept', 'multiple'];

    /**
     * Vytvoří pole typu input na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným input polem
     */
    public function create(array $block): array
    {
		$baseAttributes = [
			"required"     => parent::isRequired($block),
			"disabled"     => parent::getRule($block, "disabled", false),
			"type:file"    => $this->resolveTypeAttributes($block),
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
     * Sestaví atributy specifické pro souborové inputy.
     *
     * @param array $block Konfigurační blok
     * @return array
     */
	public function resolveTypeAttributes(array $block): array
	{
		return array_filter(
			array_combine(
				self::ALLOWED_ATTRIBUTES,
				array_map(fn($key) => $this->getRule($block, $key), self::ALLOWED_ATTRIBUTES)
			),
			fn($value) => $value !== null
		);
	}
}
