<?php
declare(strict_types=1);

namespace FieldFactory;

use FieldFactory\Factory\Form\FormFieldEnum;
use FieldFactory\Factory\FormElementFactory;
use TemplateEngine\TemplateEngine;

/**
 * Třída Form poskytuje statické API pro deklarativní vytváření polí formuláře.
 *
 * Umožňuje:
 *   - přidávat pole pomocí dynamických metod jako Form::input(...), Form::select(...)
 *   - řetězit konfiguraci (setValue, setLabel, setSmall)
 *   - vykreslit kompletní formulář přes TemplateEngine
 */
class Form
{
    /**
     * Interní buffer všech přidaných prvků formuláře.
     *
     * Každá položka je instance FieldProxy nebo obdobného wrapperu.
     *
     * @var array<int, object>
     */
    private static array $elements = [];

    /**
     * Přidá nový element do formuláře.
     *
     * Používá se interně přes __callStatic().
     *
     * @param FormFieldEnum $type Typ pole (Input, Select, Checkbox...)
     * @param string $name Atribut name=""
	 * @param string $id Atribut id=""
     * @param array $params Další metadata předaná továrně
     *
     * @return void
     */
    private static function addElement(FormFieldEnum $type, string $name, string $id, array $params = []): void
    {
		$payload = ["name" => $name, "id" => $id, ...$params];

          self::$elements[] = FormElementFactory::create(
            $type->value,
            $payload
        );
    }

    /**
     * Vykreslí všechny prvky formuláře a vyprázdní interní buffer.
     *
     * @return string HTML všech polí formuláře
     */
    public static function render(): string
    {
        $engine = new TemplateEngine([
            "base_url" => null,
            "tpl_dir" => VIEW_DIR,
            "cache_dir" => CACHE_DIR,
            "remove_comments" => true,
            "debug" => false,
        ]);

        $out = array_map(
            fn ($element) => $element->renderField($engine),
            self::$elements
        );

        self::$elements = [];

        return implode("\n", $out);
    }

    /**
     * Nastaví popisek (label) posledního přidaného pole formuláře.
     *
     * Tato metoda získá poslední prvek z interního bufferu `$elements` a zavolá na něm metodu `setFieldLabel`,
     * aby nastavila popisek pole. Pokud v bufferu není žádný prvek, metoda nic neprovede.
     *
     * @param array $args Pole argumentů obsahující text popisku a případné další parametry.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
     */
    public static function setLabel(array $args): self
    {
        $last = end(self::$elements);

        if ($last) {
            $last->setFieldLabel($args);
        }

        return new static();
    }

    /**
     * Nastaví doplňkový text (small) posledního přidaného pole formuláře.
     *
     * Tato metoda získá poslední prvek z interního bufferu `$elements` a zavolá na něm metodu `setFieldSmall`,
     * aby nastavila doplňkový text pole. Pokud v bufferu není žádný prvek, metoda nic neprovede.
     *
     * @param array $args Pole argumentů obsahující text doplňku a případné další parametry.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
     */
    public static function setSmall(array $args): self
    {
        $last = end(self::$elements);

        if ($last) {
            $last->setFieldSmall($args);
        }

        return new static();
    }

    /**
     * Magická metoda pro dynamické volání statických metod.
     *
     * Tato metoda umožňuje dynamicky volat metody pro přidání prvků formuláře,
     * jako například `Form::input(...)`, `Form::select(...)` apod.
     *
     * Nejprve se pokusí převést název metody na odpovídající hodnotu výčtu `FormFieldEnum`.
     * Pokud není název metody podporován, vyhodí výjimku `BadMethodCallException`.
     * Poté přidá hodnotu výčtu jako první argument a zavolá metodu `addElement`.
     *
     * @param string $name Název volané metody, který odpovídá typu formulářového pole.
     * @param array $arguments Argumenty předané metodě.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
     * @throws \BadMethodCallException Pokud je volána nepodporovaná metoda.
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        $enum = FormFieldEnum::tryFromCaseInsensitive($name);

        if ($enum === null) {
			$supportedMethods = implode(', ', array_map(fn($case) => $case->name, FormFieldEnum::cases()));
			throw new \BadMethodCallException("Form element '$name' není podporován. Podporované metody: $supportedMethods.");
        }

		// do argumentů vložíme element enum
        array_unshift($arguments, $enum);

        call_user_func_array([self::class, 'addElement'], $arguments);

        return new self();
    }
}