<?php
declare(strict_types=1);

namespace PlatformBridge\Form\Element;

/**
 * Proxy objekt obalující datovou strukturu pole a původní Element implementaci.
 */
class ElementProxy implements ElementInterface
{
	/**
	 * Referenční odkaz na pole s daty (proxy dostane originální data po referenci).
	 *
	 * @var array
	 */
    private array $data;

	/**
	 * Rodičovský Element, poskytuje metody sanitize() a renderFormElement().
	 *
	 * @var Element
	 */
    private Element $element;

	/**
     * Konstruktor - přijímá data referencí a instanci Element.
     *
     * @param array $data Pole dat (předáváno referencí).
     * @param Element $element Instance konkrétního pole (např. Input).
     */
    public function __construct(array &$data, Element $element)
    {
		// Uložíme referenci tak, aby změny v proxy měly vliv na originální pole
        $this->data = &$data;
        $this->element = $element;
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
    public function setElementLabel(array $args): static
    {
        $this->data['label'] = [
            'text' => $this->element->sanitize($args['text'] ?? ''),
            'class' => $this->element->sanitize($args['class'] ?? '')
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
    public function setElementSmall(array $args): static
    {
        $this->data['small'] = [
            'text' => $this->element->sanitize($args['text'] ?? ''),
            'class' => $this->element->sanitize($args['class'] ?? '')
        ];

        return $this;
    }

	/**
     * Vykreslí pole prostřednictvím původního Element objektu.
     *
     * @param object $template Šablonovací engine/renderer
     * @return string Vygenerované HTML
     */
    public function renderElement(object $template): string
    {
        return $this->element->renderFormElement($this->data, $template);
    }
}
