<?php

namespace Zoom\PlatformBridge\Handler\Fields;
use Zoom\PlatformBridge\Form\Form;

/**
 * Handler pro vytváření tick-box komponent.
 */
class TickBoxHandler extends FieldConfigurator
{
	/** @var array Podporované varianty tohoto handleru */
	private const SUPPORTED_VARIANTS = ['tick-box'];

	/** @var array Povolené atributy pro tick-box */
	private const ALLOWED_ATTRIBUTES = ['size'];

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
     * Vytvoří tick-box komponentu na základě zadaného bloku.
     *
     * @param array $block Konfigurační blok
     * @return array Pole s jedním vytvořeným tick-box polem
     */
    public function create(array $block): array
    {
		$baseAttributes = [
			'disabled'     => parent::getRule($block, 'disabled', false),
			'type:tickbox' => $this->resolveTypeAttributes($block),
		];

		$metaAttributes = parent::prepareMetaAttributes($block);

		$field = Form::TickBox(
			$block['id'],
			$block['name'],
			array_merge($baseAttributes, $metaAttributes)
		);

        parent::applyDefaults($field, $block);

        return [$field];
    }

	/**
	 * Sestaví atributy specifické pro tick-box.
	 *
	 * @param array $block Konfigurační blok
	 * @return array
	 */
	private function resolveTypeAttributes(array $block): array
	{
		$attributes = [
			'value'   => $block['value'] ?? '1',
			'size'    => $block['size'] ?? 'md',
			'checked' => parent::defaultValue('tickbox', $block),
		];

		return array_filter($attributes, fn($v) => $v !== null);
	}
}
