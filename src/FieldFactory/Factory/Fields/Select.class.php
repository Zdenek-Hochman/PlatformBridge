<?php
declare(strict_types=1);

namespace FieldFactory\Factory\Fields;

class Select extends Field
{
    private ?array $attributes = null;

	private array $common = [];

	public function __construct(array $metadataFields)
    {
		// Zavolat rodičovský konstruktor, pokud rodič očekává zachování raw dat
        parent::__construct($metadataFields);

        $this->common = $metadataFields["common"];
        $this->attributes = $metadataFields["field"]["attributes"] ?? null;
    }

	public function prepareFormFieldStructure(): object
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
			"fieldSpecificAttributes" => array_merge(
                $this->attributes ?? [],
                $dataAttributes
            ),
            "label" => [],
            "small" => []
		];

		return new \FieldFactory\Factory\Form\FieldProxy($data, $this);
	}

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
			'Select',
			['wrapWithLabel', 'renderSmall']
		);
	}
}