<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro vytváření select polí (výběrových seznamů).
 */
#[HandlerAttribute(component: ComponentType::Select)]
class SelectHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro select */
	private const ALLOWED_ATTRIBUTES = ['multiple', 'size'];

    /**
     * Vytvoří select pole na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným select polem
     */
    public function create(array $block): array
    {
		$baseAttributes = array_merge(
			[
				'required' => parent::isRequired($block),
				"disabled" => parent::getRule($block, "disabled", false),
				'options'  => $this->prepareOptions($block['options'] ?? [], $block),
			],
			$this->resolveTypeAttributes($block)
		);

		// Připraví data-* atributy z meta.
		$metaAttributes = parent::prepareMetaAttributes($block);

        // Vytvoří instanci select pole s požadovanými parametry.
		$field = Form::Select(
			$block['id'],
			$block['name'],
			array_merge($baseAttributes, $metaAttributes)
		);

        // Aplikuje výchozí hodnoty (label, small, ...)
        parent::applyDefaults($field, $block);

        // Vrací pole s jedním vytvořeným select polem.
        return [$field];
    }

    /**
     * Připraví options a označí výchozí jako selected.
     *
     * @param array $options Pole options z konfigurace
     * @param array $block Konfigurační blok
     * @return array
     */
    private function prepareOptions(array $options, array $block): array
    {
        return array_map(function ($option) use ($block) {
            return [
                'value' => $option['value'],
                'label' => $option['label'],
                'selected' => $this->defaultValue('select', $block, $option['value']),
            ];
        }, $options);
    }

	/**
	 * Sestaví atributy specifické pro select.
	 *
	 * @param array $block Konfigurační blok
	 * @return array
	 */
	private function resolveTypeAttributes(array $block): array
	{
		$attributes = [];

		foreach (self::ALLOWED_ATTRIBUTES as $key) {
			$value = $this->getRule($block, $key);
			if ($value !== null) {
				$attributes[$key] = $value;
			}
		}

		return $attributes;
	}
}