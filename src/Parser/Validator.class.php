<?php

declare(strict_types=1);

namespace Parser;

class Validator {
	/**
	 * @param array $data obsah generators.json
	 * @return array<string,array>
	 */
	protected static function validateGeneratorsConfig(array $data): array
	{
		if (!isset($data[Keys::KEY_GENERATORS->value]) || !is_array($data[Keys::KEY_GENERATORS->value])) {
			throw new \InvalidArgumentException('Config "generators.json" musí obsahovat objekt "generators".');
		}

		$generators = $data[Keys::KEY_GENERATORS->value];

		foreach ($generators as $key => $generator) {
			if (!is_array($generator)) {
				throw new \InvalidArgumentException("Generátor \"$key\" musí být objekt.");
			}

			foreach (['id', 'label', Keys::KEY_LAYOUT_REF->value] as $requiredKey) {
				if (!array_key_exists($requiredKey, $generator)) {
					throw new \InvalidArgumentException(
						"Generátor \"$key\" musí obsahovat klíč \"$requiredKey\"."
					);
				}
			}

			// volitelně můžeš zkontrolovat, že 'id' odpovídá klíči:
			if (!is_string($generator['id']) || $generator['id'] === '') {
				throw new \InvalidArgumentException("Generátor \"$key\" má neplatné \"id\".");
			}
		}

		return $generators;
	}

	/**
	 * @param array $data obsah layouts.json
	 * @return array<string,array>
	 */
	protected static function validateLayoutsConfig(array $data): array
	{
		if (!isset($data[Keys::KEY_LAYOUTS->value]) || !is_array($data[Keys::KEY_LAYOUTS->value])) {
			throw new \InvalidArgumentException('Config "layouts.json" musí obsahovat objekt "layouts".');
		}

		$layouts = $data[Keys::KEY_LAYOUTS->value];

		foreach ($layouts as $layoutId => $layout) {
			if (!is_array($layout)) {
				throw new \InvalidArgumentException("Layout \"$layoutId\" musí být objekt.");
			}

			if (!isset($layout[Keys::KEY_SECTIONS->value]) || !is_array($layout[Keys::KEY_SECTIONS->value]) || $layout[Keys::KEY_SECTIONS->value] === []) {
				throw new \InvalidArgumentException("Layout \"$layoutId\" musí obsahovat neprázdné pole \"sections\".");
			}

			foreach ($layout[Keys::KEY_SECTIONS->value] as $sectionIndex => $section) {
				if (!is_array($section)) {
					throw new \InvalidArgumentException("Sekce #$sectionIndex v layoutu \"$layoutId\" musí být objekt.");
				}

				if (!isset($section[Keys::KEY_ID->value]) || !is_string($section[Keys::KEY_ID->value]) || $section[Keys::KEY_ID->value] === '') {
					throw new \InvalidArgumentException("Sekce #$sectionIndex v layoutu \"$layoutId\" musí mít neprázdné \"id\".");
				}

				if (!isset($section[Keys::KEY_BLOCKS->value]) || !is_array($section[Keys::KEY_BLOCKS->value]) || $section[Keys::KEY_BLOCKS->value] === []) {
					throw new \InvalidArgumentException(
						"Sekce \"{$section[Keys::KEY_ID->value]}\" v layoutu \"$layoutId\" musí obsahovat neprázdné pole \"blocks\"."
					);
				}

				// Nově: každý prvek v blocks musí mít ref (inline bloky už nejsou povoleny)
				foreach ($section[Keys::KEY_BLOCKS->value] as $blockIndex => $blockDef) {
					if (!is_array($blockDef)) {
						throw new \InvalidArgumentException(
							"Blok #$blockIndex v sekci \"{$section[Keys::KEY_ID->value]}\" layoutu \"$layoutId\" musí být objekt."
						);
					}

					if (!isset($blockDef[Keys::KEY_REF->value]) || !is_string($blockDef[Keys::KEY_REF->value]) || $blockDef[Keys::KEY_REF->value] === '') {
						throw new \InvalidArgumentException(
							"Blok #$blockIndex v sekci \"{$section[Keys::KEY_ID->value]}\" layoutu \"$layoutId\" musí obsahovat platný \"ref\"."
						);
					}
				}
			}
		}

		return $layouts;
	}

	/**
	 * @param array $data obsah blocks.json
	 * @return array<string,array>
	 */
	protected static function validateBlocksConfig(array $data): array
	{
		if (!isset($data[Keys::KEY_BLOCKS->value]) || !is_array($data[Keys::KEY_BLOCKS->value])) {
			throw new \InvalidArgumentException('Config "blocks.json" musí obsahovat objekt "blocks".');
		}

		$blocks = $data[Keys::KEY_BLOCKS->value];

		foreach ($blocks as $blockKey => $block) {

			if (!is_array($block)) {
				throw new \InvalidArgumentException("Block \"$blockKey\" musí být objekt.");
			}

			foreach (['id', 'name', 'component'] as $requiredKey) {
				if (!array_key_exists($requiredKey, $block)) {
					throw new \InvalidArgumentException("Block \"$blockKey\" musí obsahovat klíč \"$requiredKey\".");
				}

				if (!is_string($block[$requiredKey]) || $block[$requiredKey] === '') {
					throw new \InvalidArgumentException("Block \"$blockKey\" má neplatné \"$requiredKey\".");
				}
			}

			if (!array_key_exists('rules', $block) || !is_array($block['rules'])) {
				throw new \InvalidArgumentException(
					"Block \"$blockKey\" musí obsahovat klíč \"rules\" typu array."
				);
			}

			// Volitelná validace klíče 'meta' (data atributy)
			if (array_key_exists('meta', $block)) {
				self::validateMetaAttributes($block['meta'], $blockKey);
			}

			$rules = $block['rules'];
			$component = $block['component'];
			$variant = $block['variant'] ?? null;

			/*
			* COMPONENT: input
			*/
			if ($component === 'input') {
				if (!is_string($variant) || $variant === '') {
					throw new \InvalidArgumentException(
						"Block \"$blockKey\" (component \"input\") musí obsahovat neprázdný \"variant\"."
					);
				}

				switch ($variant) {
					case 'text': case 'password': case 'email': case 'number': case 'url':
						// žádná speciální validace pro tyto varianty
						break;
					case 'radio':
						if (!isset($block['group']) || !is_array($block['group']) || $block['group'] === []) {
							throw new \InvalidArgumentException(
								"Block \"$blockKey\" (input + radio) musí obsahovat neprázdné \"group\"."
							);
						}

						self::validateOptionList($block['group'], $blockKey, 'group');

						$values = array_column($block['group'], 'value');

						self::validateDefaultInValues($rules, $values, $blockKey, 'input + radio');
						break;
					case 'checkbox':
						if (array_key_exists('default', $rules) && !is_bool($rules['default'])) {
							throw new \InvalidArgumentException(
								"Block \"$blockKey\" (input + checkbox) má v \"rules.default\" hodnotu, která není boolean."
							);
						}
						break;
					default:
						throw new \InvalidArgumentException(
							"Block \"$blockKey\" má neznámý input variant \"$variant\"."
						);
				}
			}

			/*
			 * COMPONENT: select
			 */
			if ($component === 'select') {
				if (!isset($block['options']) || !is_array($block['options']) || $block['options'] === []) {
					throw new \InvalidArgumentException(
						"Block \"$blockKey\" (component \"select\") musí obsahovat neprázdné \"options\"."
					);
				}

				self::validateOptionList($block['options'], $blockKey, 'options');

				$values = array_column($block['options'], 'value');

				self::validateDefaultInValues($rules, $values, $blockKey, 'select');
			}
		}

		return $blocks;
	}

	/**
	 * Cross-validace vztahů mezi blocks, layouts a generators.
	 *
	 * - Každý layout_ref v generators musí existovat v layouts
	 * - Každý ref v sekcích layoutů musí existovat v blocks
	 *
	 * @param array<string,array> $blocks
	 * @param array<string,array> $layouts
	 * @param array<string,array> $generators
	 */
	protected static function validateRelations(array $blocks, array $layouts, array $generators): void
	{
    	// 1) Generátory ↔ layouty
		foreach ($generators as $genKey => $generator) {
        	$layoutRef = $generator[Keys::KEY_LAYOUT_REF->value] ?? null;

			// tady by layout_ref už měl být string a existovat díky validateGeneratorsConfig
			if (!is_string($layoutRef) || $layoutRef === '') {
				throw new \InvalidArgumentException("Generátor \"$genKey\" má neplatný \"layout_ref\".");
			}

			if (!isset($layouts[$layoutRef])) {
				throw new \InvalidArgumentException(
					"Generátor \"$genKey\" odkazuje na neexistující layout_ref \"$layoutRef\"."
				);
			}
		}

		// 2) Layouty ↔ bloky
    	foreach ($layouts as $layoutId => $layout) {
        	$sections = $layout[Keys::KEY_SECTIONS->value] ?? [];

        	foreach ($sections as $sectionIndex => $section) {
				$sectionId = $section[Keys::KEY_ID->value] ?? "#$sectionIndex";
				$blocksInSection = $section[Keys::KEY_BLOCKS->value] ?? [];

				foreach ($blocksInSection as $blockIndex => $blockDef) {
					if (!is_array($blockDef)) {
						continue; // tohle by už stejně nemělo nastat po validateLayoutsConfig
					}

					if (!array_key_exists(Keys::KEY_REF->value, $blockDef)) {
						throw new \InvalidArgumentException(
							"Blok #$blockIndex v sekci \"$sectionId\" layoutu \"$layoutId\" musí obsahovat \"ref\"."
						);
					}

					$ref = $blockDef[Keys::KEY_REF->value] ?? null;

					if (!is_string($ref) || $ref === '') {
						throw new \InvalidArgumentException(
							"Blok #$blockIndex v sekci \"$sectionId\" layoutu \"$layoutId\" má neplatný \"ref\"."
						);
					}

					if (!isset($blocks[$ref])) {
						throw new \InvalidArgumentException(
							"Layout \"$layoutId\" (sekce \"$sectionId\") odkazuje na neznámý block ref \"$ref\"."
						);
					}
            	}
        	}
    	}
	}

	private static function validateOptionList(array $list, string $blockKey, string $fieldName): void
	{
		foreach ($list as $index => $opt) {
			if (!is_array($opt)) {
				throw new \InvalidArgumentException(
					"Prvek #$index v \"$fieldName\" blocku \"$blockKey\" musí být objekt."
				);
			}

			foreach (['value', 'label'] as $key) {
				if (!array_key_exists($key, $opt) || !is_string($opt[$key]) || $opt[$key] === '') {
					throw new \InvalidArgumentException(
						"Prvek #$index v \"$fieldName\" blocku \"$blockKey\" musí mít neprázdný \"$key\"."
					);
				}
			}
		}
	}

	private static function validateDefaultInValues(array $rules, array $values, string $blockKey, string $context): void {
		if (array_key_exists('default', $rules) && !in_array($rules['default'], $values, true)) {
			throw new \InvalidArgumentException(
				"Block \"$blockKey\" ($context) má \"rules.default\", který není v povolených hodnotách."
			);
		}
	}

	/**
	 * Validuje meta atributy (data-* atributy) pro blok.
	 *
	 * Meta musí být asociativní pole, kde klíče mohou být libovolné stringy
	 * (budou převedeny na data-* atributy s kebab-case konvencí).
	 *
	 * @param mixed $meta Hodnota klíče 'meta' z bloku
	 * @param string $blockKey Identifikátor bloku pro chybové hlášky
	 * @throws \InvalidArgumentException Pokud meta není validní
	 */
	private static function validateMetaAttributes(mixed $meta, string $blockKey): void
	{
		if (!is_array($meta)) {
			throw new \InvalidArgumentException(
				"Block \"$blockKey\" má klíč \"meta\", který musí být typu array."
			);
		}

		foreach ($meta as $key => $value) {
			if (!is_string($key) || $key === '') {
				throw new \InvalidArgumentException(
					"Block \"$blockKey\" má v \"meta\" neplatný klíč (musí být neprázdný string)."
				);
			}

			// Hodnota může být string, int, float nebo bool
			if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
				throw new \InvalidArgumentException(
					"Block \"$blockKey\" má v \"meta\" klíč \"$key\" s neplatnou hodnotou (povolené typy: string, int, float, bool)."
				);
			}
		}
	}
}