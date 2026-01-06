<?php
declare(strict_types=1);

namespace FieldFactory\Factory\Fields;

/**
 * Třída reprezentující vstupní pole (input).
 * Dědí ze základní třídy Field a poskytuje přípravu dat a vykreslení pro input pole.
 */
class Input extends Field
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
     * @param array $metadataFields Pole s metadaty, očekává klíče "common" a volitelně "input" => ["type","attributes"].
     */
    public function __construct(array $metadataFields)
    {
		// Zavolat rodičovský konstruktor, pokud rodič očekává zachování raw dat
        parent::__construct($metadataFields);

        $this->common = $metadataFields["common"];
        $this->type = $metadataFields["field"]["type"] ?? "text";
        $this->attributes = $metadataFields["field"]["attributes"] ?? null;
	}

	/**
     * Připraví datovou strukturu popisující pole pro formulář (proxy objekt).
     *
     * Vrací objekt FieldProxy, který obalí hotová data a umožní pozdější renderování.
     *
     * @return object
     */
    public function prepareFormFieldStructure(): object
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
            "fieldSpecificAttributes" => array_merge(
                $this->filterNonSpecialAttributes($this->attributes ?? []),
                $dataAttributes
            ),
        ];

		return new \FieldFactory\Factory\Form\FieldProxy($data, $this);
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
     * @param array $data Datová struktura připravená prepareFormFieldStructure().
     * @param object $engine Šablonovací engine / renderer s metodami assign() a render().
     * @return string Hotové HTML string pro pole (včetně labelu a malé nápovědy).
     */
    public function renderFormField(array $data, object $engine): string
    {
		// Vyčistit engine před novým renderem, aby se nepřenášely proměnné z předchozího renderu
		$engine->clear();

		// Přiřazení společných hodnot do engine (např. Id, Class, Name, Value, atd.)
        parent::assignCommonData($data['commonAttributes'], $engine);

		// Přiřazení extra atributů (např. data-..., maxlength=..., ...)
		parent::assignExtraData($data['fieldSpecificAttributes'], $engine);

		return parent::renderComposedField(
			$data,
			$engine,
			'Input',
			['wrapWithLabel', 'renderSmall']
		);
    }
}
