<?php

namespace Handler\Fields;
use FieldFactory\Form;

/**
 * Handler pro souborové input pole (file).
 */
class FileHandler extends FieldConfigurator
{
	/** @var array Podporované varianty tohoto handleru */
	private const SUPPORTED_VARIANTS = ['file'];

	/** @var array Povolené atributy pro souborové inputy */
	private const ALLOWED_ATTRIBUTES = ['accept', 'multiple'];

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
