<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

/**
 * Třída reprezentující tick-box komponentu.
 * Dědí ze základní třídy Element a poskytuje přípravu dat a vykreslení pro tick-box element.
 */
class TickBox extends Element
{
    /** @var array|null Dodatečné atributy definované pro tick-box */
    private ?array $attributes = null;

    /** @var array Společná konfigurace (id, class, name, atd.) */
    private array $common = [];

    /** @var bool Zda je tick-box zaškrtnutý */
    private bool $checked = false;

    /** @var string Hodnota tick-boxu */
    private string $value = '1';

    /** @var string Velikost tick-boxu (sm, md, lg) */
    private string $size = 'md';

    /**
     * Konstruktor.
     *
     * @param array $metadataElements Pole s metadaty, očekává klíče "common" a volitelně "element" => ["type","attributes"].
     */
    public function __construct(array $metadataElements)
    {
        parent::__construct($metadataElements);

        $this->common = $metadataElements["common"];
        $this->attributes = $metadataElements["element"]["attributes"] ?? null;
        $this->checked = $this->attributes['checked'] ?? false;
        $this->value = $this->attributes['value'] ?? '1';
        $this->size = $this->attributes['size'] ?? 'md';
    }

    /**
     * Připraví datovou strukturu popisující pole pro formulář (proxy objekt).
     *
     * @return object
     */
    public function prepareFormElementStructure(): object
    {
        // Připravíme data-* atributy z meta
        $dataAttributes = parent::prepareDataAttributes($this->meta);

        $data = [
            "commonAttributes" => [
                "checked" => $this->checked ? 'checked' : '',
                "disabled" => parent::prepareAttribute('disabled', $this->common),
                "id" => $this->common["id"] ?? "",
                "class" => $this->common["class"] ?? "",
                "name" => parent::formatNameAttribute($this->common["name"]),
                "value" => $this->value,
                "size" => $this->size,
            ],
            "elementSpecificAttributes" => array_merge(
                $this->filterNonSpecialAttributes($this->attributes ?? []),
                $dataAttributes
            ),
            "label" => [],
            "small" => []
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
        $specialKeys = ['checked', 'value', 'size'];
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

        // Přiřazení společných hodnot do engine
        parent::assignCommonData($data['commonAttributes'], $engine);

        // Přiřazení extra atributů
        parent::assignExtraData($data['elementSpecificAttributes'], $engine);

        return parent::renderComposedElement(
            $data,
            $engine,
            '/Element/TickBox',
            ['wrapWithLabel', 'renderSmall']
        );
    }
}
