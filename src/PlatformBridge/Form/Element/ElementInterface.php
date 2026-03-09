<?php
declare(strict_types=1);

namespace Zoom\PlatformBridge\Form\Element;

/**
 * Rozhraní pro pole formuláře.
 *
 * Definuje základní operace, které musí implementovat třídy reprezentující
 * pole formuláře: vykreslení, nastavení hodnoty, labelu a drobné nápovědy ("small").
 */
interface ElementInterface
{
	/**
     * Vykreslí pole pomocí předané šablony/engine.
     *
     * @param object $template Šablonovací engine s metodami assign() a render().
     * @return string Vrácený HTML string pole.
     */
    public function renderElement(object $template): string;

	/**
     * Nastaví data pro label pole.
     *
     * @param array $args Asociační pole argumentů pro label (např. ['text'=>'Jméno', 'class'=>'...']).
     * @return static
     */
    public function setElementLabel(array $args): static;

	/**
     * Nastaví data pro "small" (malý pomocný text).
     *
     * @param array $args Asociační pole argumentů pro small (např. ['text'=>'Nápověda']).
     * @return static
     */
    public function setElementSmall(array $args): static;
}
