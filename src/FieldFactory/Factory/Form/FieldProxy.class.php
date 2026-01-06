<?php
declare(strict_types=1);

namespace FieldFactory\Factory\Form;

use FieldFactory\Factory\Fields\Field as BaseField;

/**
 * Proxy objekt obalující datovou strukturu pole a původní Field implementaci.
 */
class FieldProxy implements FormFieldInterface
{
	/**
	 * Referenční odkaz na pole s daty (proxy dostane originální data po referenci).
	 *
	 * @var array
	 */
    private array $data;

	/**
	 * Rodičovský Field, poskytuje metody sanitize() a renderFormField().
	 *
	 * @var BaseField
	 */
    private BaseField $field;

	/**
     * Konstruktor - přijímá data referencí a instanci Field.
     *
     * @param array $data Pole dat (předáváno referencí).
     * @param BaseField $field Instance konkrétního pole (např. Input).
     */
    public function __construct(array &$data, BaseField $field)
    {
		// Uložíme referenci tak, aby změny v proxy měly vliv na originální pole
        $this->data = &$data;
        $this->field = $field;
    }

    /**
     * Nastaví label pro pole.
     *
     * Očekávaná struktura $args: ['text' => '...', 'class' => '...'] (oba volitelné).
     * Sanitizujeme textová pole. Vrací $this pro chainování.
     *
     * @param array $args
     * @return self
     */
    public function setFieldLabel(array $args): static
    {
        $this->data['label'] = [
            'text' => $this->field->sanitize($args['text'] ?? ''),
            'class' => $this->field->sanitize($args['class'] ?? '')
        ];

        return $this;
    }

	/**
     * Nastaví "small" pomocný text pro pole.
     *
     * Očekávané $args: ['text' => '...', 'class' => '...'] (oba volitelné).
     * Sanitizujeme texty. Vrací $this pro chainování.
     *
     * @param array $args
     * @return self
     */
    public function setFieldSmall(array $args): static
    {
        $this->data['small'] = [
            'text' => $this->field->sanitize($args['text'] ?? ''),
            'class' => $this->field->sanitize($args['class'] ?? '')
        ];

        return $this;
    }

	/**
     * Vykreslí pole prostřednictvím původního Field objektu.
     *
     * @param object $template Šablonovací engine/renderer
     * @return string Vygenerované HTML
     */
    public function renderField(object $template): string
    {
        return $this->field->renderFormField($this->data, $template);
    }
}
