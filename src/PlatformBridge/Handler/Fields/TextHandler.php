<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro textové input pole (text, email, password, search, url, tel).
 */
#[HandlerAttribute(component: ComponentType::Input, variants: ['text', 'email', 'password', 'search', 'url', 'tel'])]
class TextHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro textové inputy */
	private const ALLOWED_ATTRIBUTES = ['minlength', 'maxlength', 'pattern', 'size'];

    /**
     * Vytvoří pole typu input na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným input polem
     */
    public function create(array $block): array
    {
        $variant = $block['variant'] ?? 'text';

		$baseAttributes = [
			"required"        => parent::isRequired($block),
			"readonly"        => parent::getRule($block, "readonly", false),
			"disabled"        => parent::getRule($block, "disabled", false),
			"placeholder"     => parent::getRule($block, "placeholder", ""),
			"autocomplete"    => parent::getRule($block, "autocomplete", ""),
			"type:{$variant}" => $this->resolveTypeAttributes($block)
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
     * Sestaví atributy specifické pro textové inputy.
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
				'value' => $block['value'] ?? "",
			],
			fn($value) => $value !== null
		);
	}
}