<?php
declare(strict_types=1);

namespace FieldFactory\Factory\Fields;

/**
 * Třída reprezentující textarea vstupní pole.
 * Dědí ze základní třídy Field a poskytuje přípravu dat a vykreslení pro textarea pole.
 */
class Textarea extends Field
{
    /** @var array|null Dodatečné atributy definované pro textarea (např. rows, cols) */
    private ?array $attributes = null;

    /** @var array Společná konfigurace (label, id, class, name, placeholder, atd.) */
    private array $common = [];

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

        $data = [
            "commonAttributes" => [
                "readonly" => parent::prepareAttribute('readonly', $this->common),
                "disabled" => parent::prepareAttribute('disabled', $this->common),
                "required" => parent::prepareAttribute('required', $this->common),
                "id" => $this->common["id"] ?? "",
                "class" => $this->common["class"] ?? "",
                "placeholder" => $this->common["placeholder"] ?? "",
                "name" => parent::formatNameAttribute($this->common["name"]),
                "value" => "",
                "rows" => $this->attributes['rows'] ?? 4,
                "cols" => $this->attributes['cols'] ?? 50,
            ],
            "fieldSpecificAttributes" => array_merge(
                $this->attributes ?? [],
                $dataAttributes
            ),
            "label" => [],
            "small" => []
        ];

        return new \FieldFactory\Factory\Form\FieldProxy($data, $this);
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
            'Textarea',
            ['wrapWithLabel', 'renderSmall']
        );
    }
}
