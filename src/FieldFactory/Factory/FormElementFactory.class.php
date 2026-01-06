<?php
declare(strict_types=1);

namespace FieldFactory\Factory;

use FieldFactory\Factory\Form\FormValidator;
use FieldFactory\Factory\Form\FormFieldEnum;

/**
 * Továrna pro vytváření instancí polí formuláře.
 *
 * Účel:
 *  - rozparsovat vstupní konfiguraci (common + type:...),
 *  - validovat základní i typ-specifické atributy přes FormValidator,
 *  - vytvořit instanci konkrétní třídy pole a vrátit připravenou strukturu (FieldProxy / FieldProxy-like objekt).
 */
class FormElementFactory
{
    private static string $element;
    private static ?array $params = null;

    /**
     * Vytvoří element (připravený objekt/strukturu) podle zadaného element jména a dat.
     *
     * @param string $element název elementu (např. "input", "Input", "INPUT", "select", ...)
     * @param array $data asociativní pole s atributy (common a případné type:xxx => [...])
     * @return mixed instance nebo struktura, kterou používáš pro renderování (FieldProxy / FieldProxy-like object)
     */
    public static function create(string $element, array $data): mixed
    {
        self::$element = $element;
        self::$params = self::extractTypeAttributes($data);

        $commonAttributes = self::$params['common'] ?? [];
        $fieldSpec = self::$params['field'] ?? null;

		// 1) základní validace společných atributů (name/id/class/...)
        FormValidator::validateBasicAttributes($commonAttributes);

        // 2) pokud máme input-specific, validujeme a normalizujeme atributy pro ten typ
        if ($fieldSpec !== null) {
            // validateInputTypeAttributes vrací ['attributes' => [...], 'method' => 'text'|'number'|...]
            $result = FormValidator::validateInputTypeAttributes($fieldSpec['type'], $fieldSpec['attributes'] ?? []);
            // uložíme zpět normalizované a filtrované atributy do params
            self::$params['field']['attributes'] = $result['attributes'];
            // můžeme také uložit použitou "method" (užitečné pro debug)
            self::$params['field']['method'] = $result['method'];
        }

		// 3) vytvoříme instanci elementu (pomocí enumu pokud je k dispozici)
        return self::createElementInstance(self::$element, self::$params);
    }

    /**
     * Extrahuje typové atributy z $data (hledáme klíče typu "type:NAME" a zbytek považujeme za common).
     *
     * @param array $data
     * @return array ['field' => ['type'=>..., 'attributes'=>...]|null, 'common' => [...]]
     */
    private static function extractTypeAttributes(array $data): array
    {
        $fieldData = null;
        $commonAttributes = [];

        foreach ($data as $key => $value) {
            if (preg_match('/^type:([a-zA-Z]+)$/', $key, $matches)) {
                $fieldData = [
                    'type' => $matches[1],
                    'attributes' => is_array($value) ? $value : []
                ];
            } else {
                $commonAttributes[$key] = $value;
            }
        }

        return [
            'field' => $fieldData,
            'common' => $commonAttributes
        ];
    }

    /**
     * Vytvoří instanci třídy elementu a zavolá její přípravu.
     *
     * @param string $element vstupní název elementu (např. 'input')
     * @param array $params parametry připravené funkcí create()
     * @return mixed vrací to, co konkrétní Field vrací z prepareFormFieldStructure()
     * @throws \RuntimeException pokud není třída dostupná nebo prepare metoda chybí
     */
    private static function createElementInstance(string $element, array $params): mixed
    {
		// Pokusíme se nejdříve použít enum (case-insensitive)
        $enumCase = FormFieldEnum::tryFromCaseInsensitive($element);

		if ($enumCase !== null) {
			$className = $enumCase->getFieldClass();
		} else {
			// fallback: vytvoříme FQCN podle konvence
			$className = __NAMESPACE__ . "\Fields\\" . ucfirst(strtolower($element));
		}

		if (!class_exists($className)) {
            throw new \RuntimeException("Třída pro element '$element' ($className) neexistuje.");
        }

        // Vytvoříme instanci přes reflection a předáme parametry jako jediný argument (konstruktor očekává pole metadata)
        $reflection = new \ReflectionClass($className);

		// pokud konstruktor očekává více parametrů, adjustuj nové volání podle potřeby
        $instance = $reflection->newInstanceArgs([$params]);

        $methodName = "prepareFormFieldStructure";

        if (!$reflection->hasMethod($methodName)) {
            throw new \RuntimeException("Metoda $methodName neexistuje v třídě $className");
        }

		// zavoláme prepareFormFieldStructure bez parametrů (instance má v sobě data z konstruktoru)
        return $reflection->getMethod($methodName)->invoke($instance);
    }
}
