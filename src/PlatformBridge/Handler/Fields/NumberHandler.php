<?php

namespace PlatformBridge\Handler\Fields;
use PlatformBridge\Form\Form;

/**
 * Handler pro číselné input pole (number, range).
 */
class NumberHandler extends FieldConfigurator
{
	/** @var array Podporované varianty tohoto handleru */
	private const SUPPORTED_VARIANTS = ['number', 'range'];

	/** @var array Povolené atributy pro číselné inputy */
	private const ALLOWED_ATTRIBUTES = ['min', 'max', 'step'];

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
