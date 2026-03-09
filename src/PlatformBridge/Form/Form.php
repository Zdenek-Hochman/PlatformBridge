<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Form;

use Zoom\PlatformBridge\Config\ConfigKeys;
use Zoom\PlatformBridge\Form\Element\ElementType;
use Zoom\PlatformBridge\Form\Factory\FormElementFactory;
use Zoom\PlatformBridge\Runtime\LayoutManager;
use Zoom\PlatformBridge\Template\Engine;

/**
 * Statické API pro deklarativní skládání formulářových elementů.
 *
 * Form funguje jako runtime buffer:
 * - elementy se přidávají přes dynamické metody (Form::input(), ...)
 * - render() / renderWrapped() buffer vyrenderují a vyprázdní
 *
 * Používá se interně během render pipeline.
 */
final class Form
{
	/** @var list<object> */
    private static array $elements = [];

	/** @var string|null */
    private static ?string $currentBlockId = null;

    /** @var array<int, string> */
    private static array $elementBlockIds = [];

    /**
	 * Nastaví block ID pro následně přidané elementy.
	 *
	 * Používá se pro seskupení elementů do layout wrapperů
	 * při renderWrapped().
     *
     * @param string|null $blockId ID bloku z konfigurace
	 * @internal
     */
    public static function setCurrentBlock(?string $blockId): void
    {
        self::$currentBlockId = $blockId;
    }

    /**
     * Přidá element do interního bufferu.
     *
     * @param ElementType $type Typ pole (Input, Select, Checkbox...)
     * @param string $name Atribut name=""
     * @param string $id Atribut id=""
     * @param array $params Další metadata předaná továrně
     */
    private static function addElement(ElementType $type, string $name, string $id, array $params = []): void
    {
        $payload = ["name" => $name, "id" => $id, ...$params];

        self::$elements[] = FormElementFactory::create(
            $type->value,
            $payload
        );

        // Zaznamenáme block ID pro renderWrapped()
        self::$elementBlockIds[] = self::$currentBlockId ?? '__untracked_' . (count(self::$elements) - 1);
    }

    /**
     * Renderuje elementy bez layout wrapperů a vyprázdní interní buffer.
     *
     * @param Engine $engine Instance template engine pro renderování
     * @return string HTML všech polí formuláře
     */
    public static function render(Engine $engine): string
    {
        $out = array_map(
            fn ($element) => $element->renderElement($engine),
            self::$elements
        );

        self::$elements = [];

        return implode("\n", $out);
    }

    /**
	 * Renderuje elementy seskupené podle block ID
	 * a obalí je layout wrapperem.
	 *
	 * Wrapper obsahuje layout data atributy podle blockDefs.
     *
     * @param Engine $engine Instance template engine pro renderování
     * @param array $blockDefs Pole layout definic bloků (se span, row_span, ref atd.)
     * @return string HTML všech polí formuláře s wrappery
     */
    public static function renderWrapped(Engine $engine, array $blockDefs = []): string
    {
        // Sestavíme mapu blockId → blockDef pro rychlé vyhledání
        $blockDefMap = [];
        foreach ($blockDefs as $def) {
            $id = $def[ConfigKeys::ID->value] ?? $def[ConfigKeys::REF->value] ?? '';
            if ($id !== '') {
                $blockDefMap[$id] = $def;
            }
        }

        // Seskupíme elementy podle block ID (zachováme pořadí)
        $groups = [];
        $groupOrder = [];

        foreach (self::$elements as $index => $element) {
            $blockId = self::$elementBlockIds[$index] ?? '__unknown_' . $index;

            if (!isset($groups[$blockId])) {
                $groups[$blockId] = [];
                $groupOrder[] = $blockId;
            }

            $groups[$blockId][] = $element;
        }

        // Renderujeme skupiny – každá skupina = jeden layout wrapper
        $out = [];

        foreach ($groupOrder as $blockId) {
            $elements = $groups[$blockId];
            $html = '';

            foreach ($elements as $element) {
                $html .= $element->renderElement($engine);
            }

            $blockDef = $blockDefMap[$blockId] ?? [];

            $out[] = LayoutManager::wrapBlock($html, $blockDef, $blockId);
        }

        self::$elements = [];
        self::$elementBlockIds = [];
        self::$currentBlockId = null;

        return implode("\n", $out);
    }

    /**
     * Nastaví label posledního přidaného elementu.
     *
     * @param array $args Pole argumentů obsahující text popisku a případné další parametry.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
	 *
	 * @internal
     */
    public static function setLabel(array $args): self
    {
        $last = end(self::$elements);

        if ($last) {
            $last->setElementLabel($args);
        }

        return new static();
    }

    /**
     * Nastaví small posledního přidaného elementu.
     *
     * @param array $args Pole argumentů obsahující text doplňku a případné další parametry.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
	 *
	 * @internal
     */
    public static function setSmall(array $args): self
    {
        $last = end(self::$elements);

        if ($last) {
            $last->setElementSmall($args);
        }

        return new static();
    }

    /**
     * Dynamicky vytváří form element podle názvu metody.
     *
     * @param string $name Název volané metody, který odpovídá typu formulářového pole.
     * @param array $arguments Argumenty předané metodě.
     * @return self Vrací instanci třídy pro umožnění řetězení metod.
	 *
	 * @example Form::input('username', ['label' => 'Username', 'id' => 'user_id'])
	 *
     * @throws \BadMethodCallException Pokud je volána nepodporovaná metoda.
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        $enum = ElementType::tryFromCaseInsensitive($name);

        if ($enum === null) {
            $supportedMethods = implode(', ', array_map(fn ($case) => $case->name, ElementType::cases()));
            throw new \BadMethodCallException("Form element '$name' není podporován. Podporované metody: $supportedMethods.");
        }

        // do argumentů vložíme element enum
        array_unshift($arguments, $enum);

        call_user_func_array([self::class, 'addElement'], $arguments);

        return new self();
    }
}
