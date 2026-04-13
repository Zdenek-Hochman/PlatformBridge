<?php

namespace PlatformBridge\Handler\Fields;

use PlatformBridge\Handler\HandlerAttribute;

abstract class FieldConfigurator implements FieldHandler
{
    /**
     * Generická implementace supports() založená na #[HandlerAttribute].
     *
     * Čte atribut z aktuální třídy a porovnává component + variant
     * s hodnotami v konfiguračním bloku. Konkrétní handlery nemusí
     * tuto metodu přepisovat.
     *
     * @param array $block Konfigurační blok
     * @return bool
     */
    public function supports(array $block): bool
    {
        /** @var array<class-string, HandlerAttribute|null> */
        static $cache = [];

        $class = static::class;

        if (!isset($cache[$class])) {
            $ref = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(HandlerAttribute::class);
            $cache[$class] = !empty($attrs) ? $attrs[0]->newInstance() : null;
        }

        $attr = $cache[$class];

        if ($attr === null) {
            return false;
        }

        $component = $block['component'] ?? null;

        if ($component !== $attr->component->value) {
            return false;
        }

        if (empty($attr->variants)) {
            return true;
        }

        $variant = $block['variant'] ?? null;

        return in_array($variant, $attr->variants, true);
    }

    /**
     * Nastaví výchozí hodnoty pro pole na základě zadaného bloku.
     *
     * @param object $field Instance FieldProxy, na které se aplikují výchozí hodnoty
     * @param array $block  Konfigurační pole s možnými klíči 'label', 'small'
     */
    protected function applyDefaults(object $field, array $block): void
    {
        // Pokud je v bloku nastaven klíč 'label' a není prázdný, nastaví se popisek pole.
        if (!empty($block['label'])) {
            $field->setLabel(['text' => $block['label']]);
        }

        // Pokud je v bloku nastaven klíč 'small' a není prázdný, nastaví se doplňkový text pole.
        if (!empty($block['small'])) {
            $field->setSmall(['text' => $block['small']]);
        }
    }

    /**
     * Získá hodnotu pravidla z bloku.
     *
     * @param array $block Konfigurační blok
     * @param string $key Klíč pravidla
     * @param mixed $default Výchozí hodnota, pokud pravidlo není nastaveno
     * @return mixed
     */
    protected function getRule(array $block, string $key, mixed $default = null): mixed
    {
        return $block['rules'][$key] ?? $default;
    }

    /**
     * Zkontroluje, zda je pole povinné.
     *
     * @param array $block Konfigurační blok
     * @return bool
     */
    protected function isRequired(array $block): bool
    {
        return $this->getRule($block, 'required', false);
    }

	/**
     * Aplikuje výchozí hodnotu podle typu pole.
     *
     * Pro select/radio: porovnává rules.default s hodnotou iterace
     * Pro checkbox/tickbox: používá přímo rules.checked (bez porovnávání)
     *
     * @param string $type Typ pole (select, radio, checkbox, tickbox)
     * @param array $block Konfigurační blok
     * @param mixed $iteration Hodnota pro porovnání (u select/radio option value)
     * @return string|null
     */
	protected function defaultValue(string $type, array $block, mixed $iteration = null): ?string
	{
		// Checkbox / tickbox: reagují přímo na rules.checked
		if (in_array($type, ['checkbox', 'tickbox'], true)) {
			return $this->getRule($block, 'checked') ? ($type === 'tickbox' ? "i-check='true'" : 'checked') : null;
		}

		// Select / radio: porovnávají rules.default s iterací
		if ($this->getRule($block, 'default') !== $iteration) {
			return null;
		}

		return match ($type) {
			'select' => 'selected="selected"',
			'radio'  => 'checked',
			default  => null,
		};
	}
	/**
	 * Připraví data-* atributy z meta pole.
	 *
	 * Transformuje klíče z camelCase na kebab-case s prefixem "data-".
	 * Např. ['metaKey' => 'value'] => ['data-meta-key' => 'value']
	 *
	 * @param array $block Konfigurační blok obsahující klíč 'meta'
	 * @return array Asociativní pole data-* atributů
	 */
	protected function prepareMetaAttributes(array $block): array
	{
		$meta = $block['meta'] ?? [];

		if (empty($meta) || !is_array($meta)) {
			return [];
		}

		$dataAttributes = [];
		foreach ($meta as $key => $value) {
			// camelCase -> kebab-case s prefixem data-
			$attrName = 'data-' . strtolower(preg_replace('/([a-z])_?([A-Z])/', '$1-$2', $key));
			$dataAttributes[$attrName] = (string) $value;
		}

		return $dataAttributes;
	}
}