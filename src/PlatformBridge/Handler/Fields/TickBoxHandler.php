<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Form\Form;
use PlatformBridge\Handler\{HandlerAttribute, ComponentType};

/**
 * Handler pro vytváření tick-box komponent.
 */
#[HandlerAttribute(component: ComponentType::TickBox)]
class TickBoxHandler extends FieldConfigurator
{
	/** @var array Povolené atributy pro tick-box */
	private const ALLOWED_ATTRIBUTES = ['size'];

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
