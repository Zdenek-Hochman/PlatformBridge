<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

/**
 * Třída reprezentující vstupní pole (input).
 * Dědí ze základní třídy Element a poskytuje přípravu dat a vykreslení pro input pole.
 */
class Input extends Element
{
	/** @var array|null Dodatečné atributy definované pro input (např. maxlength, pattern) */
    private ?array $attributes = null;

	/** @var array Společná konfigurace (label, id, class, name, placeholder, atd.) */
    private array $common = [];

  	/** @var string Typ inputu (text, email, number, ...). */
    private string $type = "text";

    /**
     * Konstruktor.
     *
     * @param array $metadataElements Pole s metadaty, očekává klíče "common" a volitelně "input" => ["type","attributes"].
     */
    public function __construct(array $metadataElements)
    {
		// Zavolat rodičovský konstruktor, pokud rodič očekává zachování raw dat
        parent::__construct($metadataElements);

        $this->common = $metadataElements["common"];
        $this->type = $metadataElements["element"]["type"] ?? "text";
        $this->attributes = $metadataElements["element"]["attributes"] ?? null;
	}

	/**
     * Připraví datovou strukturu popisující pole pro formulář (proxy objekt).
     *
     * Vrací objekt ElementProxy, který obalí hotová data a umožní pozdější renderování.
     *
     * @return object
     */
    public function prepareFormElementStructure(): object
    {
        // Připravíme data-* atributy z meta
        $dataAttributes = parent::prepareDataAttributes($this->meta);

        // Povinné atributy - vždy přítomné
        $requiredAttributes = [
            "type" => $this->type,
            "id" => $this->common["id"] ?? "",
            "name" => parent::formatNameAttribute($this->common["name"]),
        ];

		// Volitelné atributy - přidají se pouze pokud nejsou prázdné
        $optionalAttributes = $this->filterEmptyAttributes([
            "class" => $this->common["class"] ?? "",
            "placeholder" => parent::preparePlaceholder($this->common),
            "autocomplete" => parent::prepareAutocomplete($this->common),
            "readonly" => parent::prepareAttribute('readonly', $this->common),
            "disabled" => parent::prepareAttribute('disabled', $this->common),
            "required" => parent::prepareAttribute('required', $this->common),
            "value" => $this->attributes['value'] ?? "",
			"checked" => $this->attributes['checked'] ?? "",
        ]);

        $data = [
            "commonAttributes" => array_merge($requiredAttributes, $optionalAttributes),
            "elementSpecificAttributes" => array_merge(
                $this->filterNonSpecialAttributes($this->attributes ?? []),
                $dataAttributes
            ),
        ];

		return new ElementProxy($data, $this);
    }

    /**
     * Odfiltruje speciální atributy, které se zpracovávají jinak.
     *
     * @param array $attributes
     * @return array
     */
    private function filterNonSpecialAttributes(array $attributes): array
    {
        $specialKeys = ['checked', 'value'];
        return array_filter(
            $attributes,
            fn($key) => !in_array($key, $specialKeys, true),
            ARRAY_FILTER_USE_KEY
        );
    }

	/**
     * Vykreslí pole pomocí dodaného template/engine objektu.
     *
     * @param array $data Datová struktura připravená prepareFormElementStructure().
     * @param object $engine Šablonovací engine / renderer s metodami assign() a render().
     * @return string Hotové HTML string pro pole (včetně labelu a malé nápovědy).
     */
    public function renderFormElement(array $data, object $engine): string
    {
		// Vyčistit engine před novým renderem, aby se nepřenášely proměnné z předchozího renderu
		$engine->clear();

		// Přiřazení společných hodnot do engine (např. Id, Class, Name, Value, atd.)
        parent::assignCommonData($data['commonAttributes'], $engine);

		// Přiřazení extra atributů (např. data-..., maxlength=..., ...)
		parent::assignExtraData($data['elementSpecificAttributes'], $engine);

		return parent::renderComposedElement(
			$data,
			$engine,
			'/Element/Input',
			['wrapWithLabel', 'renderSmall']
		);
    }
}
