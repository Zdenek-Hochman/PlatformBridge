<?php

namespace TemplateEngine;

const PHP_WRAP_START = '<?php ';
const PHP_WRAP_END = ' ?>';

/**
 * Třída VariableModifier slouží k modifikaci proměnných v šablonách.
 */
class VariableModifier
{
    /**
     * Transformuje proměnné v HTML textu.
     *
     * Tato metoda nahrazuje proměnné v HTML textu pomocí regulárních výrazů.
     * Pokud je zapnutý escape mód, tak se proměnné escapují pomocí funkce htmlspecialchars.
     * Pokud je zapnutý echo mód, tak se proměnné vypisují pomocí funkce echo.
     *
     * @param string $html HTML text obsahující proměnné
     * @param bool $escape Určuje, zda se mají proměnné escapovat
     * @param bool $echo Určuje, zda se mají proměnné vypisovat
     * @return string Transformovaný HTML text
     */
    protected static function transformHtmlVariables(string $html, bool $escape = true, bool $echo = true): string
    {

        $html = preg_replace('/\{(?:raw\s+)?(\$.*?)\}/m', '$1', $html);

        if (!preg_match_all('/(\$[a-z_A-Z][^\s]*)/', $html, $matches)) {
            return $html;
        }

        foreach ($matches[1] as $match) {
            $rep = preg_replace([
                '/\[(\${0,1}[a-zA-Z_0-9]*)\]/',
                '/\.(\${0,1}[a-zA-Z_0-9]*(?![a-zA-Z_0-9]*(\'|\")))/'
            ], [
                '["$1"]',
                '["$1"]'
            ], $match);

            $html = str_replace($match, $rep, $html);
        }

        $html = self::replaceTemplateModifiers($html);

        if (!preg_match('/\$.*=.*/', $html)) {
            if ($escape) {
                // $html = "htmlspecialchars($html, ENT_HTML5, 'UTF-8', false)";
            }

            if ($echo) {
                $html = "echo $html";
            }
        }

        return $html;
    }

    protected static function replaceTemplateModifiers(string $html): string
    {
        // Opakujte, dokud se v HTML kódu nachází znak '|', který není součástí dvojice '|'
        while (strpos($html, '|') !== false && substr($html, strpos($html, '|') + 1, 1) != "|") {
            // Pokusí se najít vzor modifikátoru v HTML kódu
            if (!preg_match('/([\$a-z_A-Z0-9\(\),\[\]"->]+)\|([\$a-z_A-Z0-9\(\):,\[\]"->\s]+)/i', $html, $result)) {
                break;
            }

            // Extrahuje parametry a modifikátory z nalezeného vzoru
            list($fullMatch, $functionParams, $modifiers) = $result;
            $modifiers = str_replace("::", "@double_dot@", $modifiers);
            list($function, $params) = array_pad(explode(":", $modifiers, 2), 2, "");

            // Obnoví dvojtečky z nahrazených modifikátorů a připraví volání funkce
            $function = str_replace('@double_dot@', '::', $function);
            $params = $params ? "," . $params : "";

            // Zpracuje funkci podle jejího typu
            $html = self::processFunction($html, $fullMatch, $function, $functionParams, $params);
        }

        return $html;
    }

    private static function processFunction(string $html, string $fullMatch, string $function, string $functionParams, string $params): string
    {
        if (function_exists($function)) {
            // Nahrazuje vzor modifikátoru v HTML kódu za odpovídající PHP funkci
            return str_replace($fullMatch, $function . "(" . $functionParams . $params . ")", $html);
        } elseif (method_exists(__CLASS__, $function)) {
            // Dynamicky zavolá metodu podle názvu funkce
            $method = new \ReflectionMethod(__CLASS__, $function);
            $escapedValue = $method->invoke(null, $functionParams, trim($params, ', '));
            return str_replace($fullMatch, $escapedValue, $html);
        } else {
            // Vyvolá chybu, pokud funkce neexistuje
            throw new \Exception("Function $function does not exist.");
        }
    }

    /**
     * Tato metoda nahrazuje konstanty v HTML řetězci.
     *
     * @param string $html Vstupní HTML řetězec obsahující konstanty.
     * @return string Výsledný řetězec po nahrazení konstanty.
     */
    protected static function replaceConstantModifier(string $html): string
    {
        return "echo " . str_replace(['"', "'"], "", $html);
    }

    /**
     * Metoda addPhpWrap() přidává začátek a konec PHP kolem zadaného řetězce.
     *
     * @param string $string Řetězec, ke kterému se mají přidat PHP závorky.
     * @return string Řetězec s přidanými PHP závorkami.
     */
    protected static function addPhpWrap(string $string): string
    {
        return trim(PHP_WRAP_START . $string . PHP_WRAP_END);
    }

    /**
     * Přidává jednoduché uvozovky kolem zadaného řetězce.
     *
     * @param string $string Řetězec, ke kterému se mají přidat uvozovky.
     * @return string Řetězec s přidanými uvozovkami.
     */
    protected static function addPhpQuote(string $string): string
    {
        return "'" . $string . "'";
    }

    protected static function escape(string $string, string $type): string
    {
        switch ($type) {
            case 'html':
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            case 'htmlall':
                return htmlentities($string, ENT_QUOTES, 'UTF-8');
            case 'url':
                return self::escapeUrl($string);
            case 'quotes':
                return addslashes($string);
            case 'mail':
                return str_replace(['@', '.'], ['&#64;', '&#46;'], $string);
            case 'hex':
                return self::escapeHex($string);
            case 'urlencode':
                return urlencode($string);
            default:
                throw new \InvalidArgumentException("Unknown escape type: $type");
        }
    }

    protected static function empty(string $string): bool
    {
        return "empty($string)";
    }

    protected static function not_empty(string $string): bool
    {
        return "!empty($string)";
    }

    private static function escapeUrl(string $string): string
    {
        // Definujte regulární výraz pro nebezpečné znaky v URL
        $pattern = '/[^a-zA-Z0-9_\-\.~]/';

        // Použijte preg_replace_callback k nahrazení nebezpečných znaků
        return preg_replace_callback($pattern, function ($matches) {
            return rawurlencode($matches[0]);
        }, $string);
    }

    private static function escapeHex(string $string): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= '%' . bin2hex($string[$i]);
        }
        return $hex;
    }
}
