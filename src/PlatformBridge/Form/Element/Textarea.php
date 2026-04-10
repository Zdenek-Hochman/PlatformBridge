<?php
declare(strict_types=1);

namespace PlatformBridge\Form\Element;

/**
 * Třída reprezentující textarea vstupní pole.
 * Dědí ze základní třídy Element a poskytuje přípravu dat a vykreslení pro textarea pole.
 */
class Textarea extends Element
{
    /** @var array|null Dodatečné atributy definované pro textarea (např. rows, cols) */
    private ?array $attributes = null;

    /** @var array Společná konfigurace (label, id, class, name, placeholder, atd.) */
    private array $common = [];

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
            "elementSpecificAttributes" => array_merge(
                $this->attributes ?? [],
                $dataAttributes
            ),
            "label" => [],
            "small" => []
        ];

        return new ElementProxy($data, $this);
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
            '/Element/Textarea',
            ['wrapWithLabel', 'renderSmall']
        );
    }
}
