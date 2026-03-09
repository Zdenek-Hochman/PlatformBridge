<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

/**
 * Validátor formulářových polí.
 *
 * Obsahuje základní seznam povolených společných atributů a metody pro:
 *  - základní validaci atributů (typy a povolené klíče),
 *  - validaci atributů specifických pro jednotlivé typy inputů,
 *  - filtrování atributů podle povoleného seznamu.
 */
class ElementValidator
{
    /**
     * @var array Seznam sdílených povolených základních atributů a jejich očekávané typy.
     */
    public const ALLOWED_ATTRIBUTES = [
        'name' => 'string',
        'value' => 'string',
        'id' => 'string',
        'class' => 'string',
        'placeholder' => 'string',
        'required' => 'boolean',
        'disabled' => 'boolean',
        'readonly' => 'boolean',
        'autocomplete' => 'string',
		'options' => 'array',
    ];

	/**
	 * Povolené prefixy pro atributy, které mohou být předány do HTML elementů.
	 *
	 * Umožňuje např. libovolné data-* a aria-* atributy, které nejsou explicitně uvedeny v ALLOWED_ATTRIBUTES.
	 *
	 * @var array
	 */
	private const ALLOWED_ATTRIBUTE_PREFIXES = [
		'data-',
		'aria-'
	];

	/**
     * Atributy, které se pro konkrétní typy budou normalizovat na integer.
     * Mapu použijeme při normalizaci pro konkrétní input typu.
     */
    private const INT_ATTRIBUTES_PER_TYPE = [
        'text' => ['maxlength', 'minlength', 'size'],
        'number' => ['min', 'max', 'step'],
        'date' => ['min', 'max', 'step'],
        'checkbox' => [], // žádné int
        'file' => [], // žádné int
        'button' => [], // žádné int
        'image' => ['width', 'height'],
    ];

    /**
     * Atributy, které se budou normalizovat na float (desetinná čísla).
     * Používáme to především pro 'step', ale lze sem přidat i jiné klíče.
     */
    private const FLOAT_ATTRIBUTES_PER_TYPE = [
        'number' => ['step', 'min', 'max'],
        'date' => ['step'], // u date může step být specifikovaný jako interval
        'image' => [],
        'text' => [],
    ];

	/**
     * Atributy, které se budou normalizovat na boolean (pokud jsou přítomny).
     * Některé z nich jsou v ALLOWED_ATTRIBUTES (common), jiné jsou per-typ (např. checked, multiple).
     */
    private const BOOL_ATTRIBUTES = [
        'required', 'disabled', 'readonly', 'multiple'
    ];

    /**
     * Provede základní validaci atributů.
     *
     * Kontroluje:
     *  - že klíče atributů jsou povolené (jsou v ALLOWED_ATTRIBUTES nebo odpovídají patternu typu "type:Something"),
     *  - že hodnoty mají správné typy podle ALLOWED_ATTRIBUTES.
     *
     * @param array $attributes Asociační pole atributů, které se mají validovat.
     * @throws \InvalidArgumentException Pokud narazí na nepovolený klíč nebo špatný typ.
     */
    public static function validateBasicAttributes(array $attributes): void
    {
		// Nejprve normalizujeme common boolean-like hodnoty (např. "1" -> true)
        $attributes = self::normalizeCommonAttributes($attributes);

        array_map(function ($key) use ($attributes) {
			 // Povolené klíče: buď obecné ALLOWED_ATTRIBUTES, nebo speciální pattern "type:NAME"
            if (
				!array_key_exists($key, self::ALLOWED_ATTRIBUTES)
				&& !preg_match('/^type:[a-zA-Z]+$/', $key)
  				&& !self::isAllowedPrefixedAttribute($key)
			) {
                throw new \InvalidArgumentException("Attribute $key is not allowed.");
            }

			 // Pokud je klíč v ALLOWED_ATTRIBUTES, zkontroluj typ hodnoty
            if (array_key_exists($key, self::ALLOWED_ATTRIBUTES)) {
                $expectedType = self::ALLOWED_ATTRIBUTES[$key];
                $actualType = gettype($attributes[$key]);

                if ($expectedType !== $actualType) {
                    throw new \InvalidArgumentException("Attribute $key must be of type $expectedType, $actualType given.");
                }
            }
        }, array_keys($attributes));
    }

    /**
     * Validuje atributy specifické pro daný HTML input typ.
     *
     * Vrací asociativní pole s klíči:
     *  - 'attributes' => pole pro daný typ (filtrované a validované),
     *  - 'method' => název internej skupiny atributů (např. 'text', 'number', ...).
     *
     * Implementace mapuje konkrétní HTML typy (např. "email", "password") na interní
     * skupiny metod (např. "text"). Poté zavolá příslušnou privátní statickou metodu
     * pro filtrování těchto atributů.
     *
     * @param string $type Typ inputu (např. "text", "email", "number", "date" ...).
     * @param array $attributes Pole atributů, která chceme filtrovat / validovat.
     * @return array ['attributes' => array, 'method' => string]
     * @throws \InvalidArgumentException Pokud typ není podporován.
     */
    public static function validateInputTypeAttributes(string $type, array $attributes): array
    {
        $methodTypeMap = [
            'text' => ['text', 'password', 'email', 'search', 'tel', 'url', 'hidden'],
            'number' => ['number', 'range'],
            'date' => ['date', 'datetime-local', 'month', 'week', 'time'],
            'checkbox' => ['checkbox', 'radio'],
            'file' => ['file'],
            'button' => ['button', 'submit', 'reset'],
            'image' => ['image'],
        ];

        foreach ($methodTypeMap as $method => $types) {
            if (in_array($type, $types, true)) {
				// Normalizovat atributy (int/boolean) pro tuto skupinu
                $normalized = self::normalizeAttributesForType($method, $attributes);

				// dynamicky zavoláme privátní metodu (např. self::text($attributes))
               	$allowedAttributes = call_user_func([self::class, $method], $normalized);

				return ['attributes' => $allowedAttributes, 'method' => $method];
            }
        }

        throw new \InvalidArgumentException("Unsupported input type: $type");
    }

	/**
     * Normalizace společných atributů (převod truthy/falsy na boolean pokud je klíč v BOOL_ATTRIBUTES).
     *
     * @param array $attributes
     * @return array
     */
    private static function normalizeCommonAttributes(array $attributes): array
    {
        foreach (self::BOOL_ATTRIBUTES as $boolKey) {
            if (array_key_exists($boolKey, $attributes)) {
                $attributes[$boolKey] = self::toBoolean($attributes[$boolKey]);
            }
        }
        return $attributes;
    }

	/**
     * Normalizace atributů pro konkrétní interní typ (např. 'text','number','image'...).
     * Převádí vybrané klíče na integer a boolean dle konfigurace.
     *
     * @param string $method Interní metoda/ skupina (text, number, date, ...)
     * @param array $attributes
     * @return array Normalizované atributy
     */
    private static function normalizeAttributesForType(string $method, array $attributes): array
    {
        // Normalizace boolean pro běžné boolean-like klíče (checked, multiple)
        foreach (self::BOOL_ATTRIBUTES as $b) {
            if (array_key_exists($b, $attributes)) {
                $attributes[$b] = self::toBoolean($attributes[$b]);
            }
        }

        // Normalizace integer pro definované klíče této skupiny
        $intKeys = self::INT_ATTRIBUTES_PER_TYPE[$method] ?? [];
        foreach ($intKeys as $k) {
            if (array_key_exists($k, $attributes) && $attributes[$k] !== '') {
                $attributes[$k] = self::toNumberOrLeave($attributes[$k], false);
            }
        }

		// Float klíče (např. step) — pokud jsou přítomny, pokusíme se vrátit float/int podle formátu
        $floatKeys = self::FLOAT_ATTRIBUTES_PER_TYPE[$method] ?? [];
        foreach ($floatKeys as $k) {
            if (array_key_exists($k, $attributes) && $attributes[$k] !== '') {
                $attributes[$k] = self::toNumberOrLeave($attributes[$k],  true);
            }
        }

        return $attributes;
    }

	  /**
     * Pokusí se převést hodnotu na integer pokud má numerický tvar.
     * Pokud to není možné, vrátí původní hodnotu (string apod.).
     *
     * @param mixed $value
     * @return mixed
     */
    private static function toNumberOrLeave(mixed $value, bool $preferFloat = false): mixed
    {
		if (is_int($value) || is_float($value)) {
            return $value;
        }

		if (!is_string($value) && !is_numeric($value)) {
            return $value;
        }

 		// trim a české/neobvyklé mezery odstraníme
        $val = trim((string)$value);

        // povolíme i čísla s desetinnou čárkou nebo tečkou; normalize to dot
        $valNormalized = str_replace(',', '.', $val);

        if (!is_numeric($valNormalized)) {
            return $value;
        }

        // je-li preferFloat, nebo obsahuje tečku/exp formát -> float
        if ($preferFloat || strpos($valNormalized, '.') !== false || stripos($valNormalized, 'e') !== false) {
            return (float)$valNormalized;
        }

        // jinak vrátíme int
        return (int)$valNormalized;
    }

	/**
     * Převod na boolean: podporuje různé truthy/falsy formy.
     *
     * @param mixed $value
     * @return bool
     */
    private static function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
    }

    /**
     * Filtrovat atributy podle povolených klíčů.
     *
     * Pokud $params obsahuje nějaké klíče, které nejsou v $allowedKeys, bude
     * vyhozena výjimka s výpisem nepovolených klíčů.
     *
     * @param array $params Atributy k filtrování.
     * @param array $allowedKeys Seznam povolených klíčů.
     * @return array Filtrované pole obsahující pouze povolené klíče.
     * @throws \InvalidArgumentException Pokud jsou nalezeny nepovolené klíče.
     */
    private static function filterAttributes(array $params, array $allowedKeys): array
    {
		// Najdeme nepovolené klíče (ty, které nejsou v allowedKeys)
        $invalidKeys = array_diff_key($params, array_flip($allowedKeys));

        // Pokud existují nepovolené klíče, vyhodíme výjimku
        if (!empty($invalidKeys)) {
            $invalidKeysString = implode(', ', array_keys($invalidKeys));
            throw new \InvalidArgumentException("Nepovolené atributy: $invalidKeysString");
        }

  		// Vrátíme jen povolené atributy (intersect podle klíčů)
        return array_intersect_key($params, array_flip($allowedKeys));
    }

    /**
     * Zjistí, zda klíč atributu začíná jedním z povolených prefixů (např. "data-", "aria-").
     *
     * Používá se pro povolení libovolných data-* nebo aria-* atributů, které nejsou explicitně uvedeny v seznamu povolených klíčů.
     *
     * @param string $key Klíč atributu (např. "data-id", "aria-label").
     * @return bool True pokud je prefix povolený, jinak false.
     */
    private static function isAllowedPrefixedAttribute(string $key): bool
    {
        foreach (self::ALLOWED_ATTRIBUTE_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

	/**
     * Povolené atributy pro text-like inputy.
     *
     * @param array $params
     * @return array
     */
    private static function text(array $params): array
    {
        return self::filterAttributes($params, ['maxlength', 'minlength', 'pattern', 'size', 'value']);
    }

	 /**
     * Povolené atributy pro number-like inputy.
     *
     * @param array $params
     * @return array
     */
    private static function number(array $params): array
    {
        return self::filterAttributes($params, ['min', 'max', 'step', 'value']);
    }

	/**
     * Povolené atributy pro date-like inputy.
     *
     * @param array $params
     * @return array
     */
    private static function date(array $params): array
    {
        return self::filterAttributes($params, ['min', 'max', 'step', 'value']);
    }

	/**
     * Povolené atributy pro checkbox/radio inputy.
     *
     * @param array $params
     * @return array
     */
    private static function checkbox(array $params): array
    {
        return self::filterAttributes($params, ['checked', 'value']);
    }

	/**
     * Povolené atributy pro file input.
     *
     * @param array $params
     * @return array
     */
    private static function file(array $params): array
    {
        return self::filterAttributes($params, ['accept', 'multiple', 'value']);
    }

	/**
     * Povolené atributy pro button-like inputy.
     *
     * @param array $params
     * @return array
     */
    private static function button(array $params): array
    {
        return self::filterAttributes($params, ['value']);
    }

	/**
     * Povolené atributy pro image input.
     *
     * @param array $params
     * @return array
     */
    private static function image(array $params): array
    {
        return self::filterAttributes($params, ['src', 'alt', 'width', 'height']);
    }
}
