<?php

namespace PlatformBridge\Handler\Fields;
use PlatformBridge\Form\Form;

/**
 * Handler pro vytváření textarea polí.
 */
class TextareaHandler extends FieldConfigurator
{
	/** @var array Podporované varianty tohoto handleru */
	private const SUPPORTED_VARIANTS = ['textarea'];

	/** @var array Povolené atributy pro textarea */
	private const ALLOWED_ATTRIBUTES = ['rows', 'cols', 'minlength', 'maxlength', 'wrap'];

    /**
     * Určuje, zda tento handler podporuje zadaný blok.
     *
     * @param array $block Konfigurační blok
     * @return bool
     */
    public function supports(array $block): bool
    {
        return in_array($block['component'] ?? null, self::SUPPORTED_VARIANTS, true);
    }

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
