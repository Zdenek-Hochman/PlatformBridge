<?php
declare(strict_types=1);

namespace FieldFactory\Factory\Form;

/**
 * Výčet typů polí formuláře.
 *
 * Backed enum (string) — hodnota enumu je základní jméno třídy pole,
 *
 * Přidané helpery:
 *  - getFieldClass(): vrátí FQCN třídy pole (např. \FieldFactory\Factory\Fields\Input)
 *  - toKey(): vrátí "klíč" v malých písmenech (např. 'input') pro indexování datových struktur
 *  - tryFromCaseInsensitive(): pokusí se najít case-insensitive enum podle jména nebo hodnoty
 */
enum FormFieldEnum: string
{
    case INPUT = 'Input';
    case SELECT = 'Select';
    case TEXTAREA = 'Textarea';
    case TICKBOX = 'TickBox';

	/**
     * Vrátí plně kvalifikované jméno třídy (FQCN) pro daný typ pole.
     *
     * @return string Např. "\FieldFactory\Factory\Fields\Input"
     */
    public function getFieldClass(): string
    {
        return "\\FieldFactory\\Factory\\Fields\\" . $this->value;
    }

	/**
     * Vrátí klíč v malých písmenech, který používáš v datové struktuře (např. 'input').
     *
     * @return string
     */
    public function toKey(): string
    {
        return strtolower($this->value);
    }

	/**
     * Pokusí se získat enum case podle stringu (case-insensitive).
     * Hledá jak podle jména case (INPUT), tak podle hodnoty ('Input'), a vrací null pokud nic nepasuje.
     *
     * @param string $input
     * @return self|null
     */
    public static function tryFromCaseInsensitive(string $input): ?self
    {
        $needle = strtolower($input);
        foreach (self::cases() as $case) {
            if (strtolower($case->name) === $needle || strtolower($case->value) === $needle) {
                return $case;
            }
        }
        return null;
    }
}
