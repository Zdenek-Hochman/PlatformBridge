<?php
declare(strict_types=1);

namespace PlatformBridge\Form\Element;

/**
 * Výčet typů polí formuláře.
 *
 * Backed enum (string) — hodnota enumu je základní jméno třídy pole,
 *
 */
enum ElementType: string
{
    case INPUT = 'Input';
    case SELECT = 'Select';
    case TEXTAREA = 'Textarea';
    case TICKBOX = 'TickBox';

	/**
     * Vrátí plně kvalifikované jméno třídy (FQCN) pro daný typ pole.
     *
     * @return string Např. "\Form\Element\Input"
     */
    public function getElementClass(): string
    {
        return "\\PlatformBridge\\Form\\Element\\" . $this->value;
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
