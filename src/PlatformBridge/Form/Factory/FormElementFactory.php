<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Factory;

use Zoom\PlatformBridge\Form\Element\ElementValidator;
use Zoom\PlatformBridge\Form\Element\ElementType;

/**
 * Factory pro vytvoření formulářového elementu z konfigurace.
 *
 * Zodpovídá za:
 * - rozdělení common / type-specific atributů
 * - validaci atributů
 * - vytvoření a inicializaci konkrétního elementu
 */
class FormElementFactory
{
    /**
     * Vytvoří form element z konfigurace.
     *
     * @param string $element Název elementu
     * @param array $data Konfigurace elementu
     * @return mixed
	 *
     */
    public static function create(string $element, array $data): mixed
    {
		$params = self::extractTypeAttributes($data);

        $commonAttributes = $params['common'] ?? [];
        $elementSpec = $params['element'] ?? null;

        ElementValidator::validateBasicAttributes($commonAttributes);

        if ($elementSpec !== null) {
            $result = ElementValidator::validateInputTypeAttributes($elementSpec['type'], $elementSpec['attributes'] ?? []);

            $params['element']['attributes'] = $result['attributes'];
            $params['element']['method'] = $result['method'];
        }

        return self::createElementInstance($element, $params);
    }

    /**
     * Extrahuje typové atributy z $data (hledáme klíče typu "type:NAME" a zbytek považujeme za common).
     *
     * @param array $data
     * @return array{element:?array,common:array,meta:array}
     */
    private static function extractTypeAttributes(array $data): array
    {
        $elementData = null;
        $commonAttributes = [];
        $metaAttributes = [];

        foreach ($data as $key => $value) {
            if (preg_match('/^type:([a-zA-Z]+)$/', $key, $matches)) {
                $elementData = [
                    'type' => $matches[1],
                    'attributes' => is_array($value) ? $value : []
                ];
            } elseif (str_starts_with($key, 'data-') || str_starts_with($key, 'aria-')) {
                $metaAttributes[$key] = $value;
            } else {
                $commonAttributes[$key] = $value;
            }
        }

        return [
            'element' => $elementData,
            'common' => $commonAttributes,
            'meta' => $metaAttributes
        ];
    }

    /**
     * Vytvoří instanci třídy elementu a zavolá její přípravu.
     *
     * @param string $element vstupní název elementu (např. 'input')
     * @param array $params parametry připravené funkcí create()
	 *
     * @return mixed vrací to, co konkrétní Element vrací z prepareFormElementStructure()
	 * @see \Zoom\PlatformBridge\Form\Element\ElementInterface::prepareFormElementStructure()
	 *
     * @throws \RuntimeException pokud není třída dostupná nebo prepare metoda chybí
     */
    private static function createElementInstance(string $element, array $params): mixed
    {
        $enumCase = ElementType::tryFromCaseInsensitive($element);

		if ($enumCase !== null) {
			$className = $enumCase->getElementClass();
		} else {
			$className = __NAMESPACE__ . "\Element\\" . ucfirst(strtolower($element));
		}

		if (!class_exists($className)) {
            throw new \RuntimeException("Třída pro element '$element' ($className) neexistuje.");
        }

        $reflection = new \ReflectionClass($className);

        $instance = $reflection->newInstanceArgs([$params]);

        $methodName = "prepareFormElementStructure";

        if (!$reflection->hasMethod($methodName)) {
            throw new \RuntimeException("Metoda $methodName neexistuje v třídě $className");
        }

        return $reflection->getMethod($methodName)->invoke($instance);
    }
}
