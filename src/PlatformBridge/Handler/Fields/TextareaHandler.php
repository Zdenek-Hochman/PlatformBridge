<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro vytváření textarea polí.
 */
#[HandlerAttribute(component: ComponentType::Textarea)]
class TextareaHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro textarea */
	private const ALLOWED_ATTRIBUTES = ['rows', 'cols', 'minlength', 'maxlength', 'wrap'];

    /**
     * Vytvoří textarea pole na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným textarea polem
     */
    public function create(array $block): array
    {
		$baseAttributes = array_merge(
			[
				'required'    => parent::isRequired($block),
				'disabled'    => parent::getRule($block, 'disabled', false),
				'readonly'    => parent::getRule($block, 'readonly', false),
				'placeholder' => parent::getRule($block, 'placeholder', ''),
			],
			$this->resolveTypeAttributes($block)
		);

		$metaAttributes = parent::prepareMetaAttributes($block);

		$field = Form::Textarea(
			$block['id'],
			$block['name'],
			array_merge($baseAttributes, $metaAttributes)
		);

        parent::applyDefaults($field, $block);

        return [$field];
    }

	/**
	 * Sestaví atributy specifické pro textarea.
	 *
	 * @param array $block Konfigurační blok
	 * @return array
	 */
	private function resolveTypeAttributes(array $block): array
	{
		$attributes = [
			'rows' => $block['rows'] ?? 4,
			'cols' => $block['cols'] ?? 50,
		];

		foreach (self::ALLOWED_ATTRIBUTES as $key) {
			if (in_array($key, ['rows', 'cols'])) continue; // už nastaveno výše

			$value = $this->getRule($block, $key);
			if ($value !== null) {
				$attributes[$key] = $value;
			}
		}

		return $attributes;
	}
}
