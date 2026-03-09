<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

class Select extends Element
{
    private ?array $attributes = null;

	private array $common = [];

	public function __construct(array $metadataElements)
    {
		// Zavolat rodičovský konstruktor, pokud rodič očekává zachování raw dat
        parent::__construct($metadataElements);

        $this->common = $metadataElements["common"];
        $this->attributes = $metadataElements["element"]["attributes"] ?? null;
    }

	public function prepareFormElementStructure(): object
    {
		// Připravíme data-* atributy z meta
        $dataAttributes = parent::prepareDataAttributes($this->meta);

		$data = [
			"commonAttributes" => [
                "disabled" => parent::prepareAttribute('disabled', $this->common),
                "required" => parent::prepareAttribute('required', $this->common),
				"name" => parent::formatNameAttribute($this->common["name"]),
				"id" => $this->common["id"] ?? "",
                "class" => $this->common["class"] ?? "",
				"options" => $this->common["options"] ?? []
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
			'/Element/Select',
			['wrapWithLabel', 'renderSmall']
		);
	}
}