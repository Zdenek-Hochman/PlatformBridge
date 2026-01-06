<?php

namespace Translator;

/**
 * Třída Translator zajišťuje překlady textů v aplikaci.
 *
 * Podporuje:
 *   - načítání překladů ze souborů (JSON)
 *   - fallback na výchozí hodnotu
 *   - interpolaci proměnných v překladech
 */
class Translator
{
    /** @var string Aktuální jazyk */
    private static string $locale = 'cs';

    /** @var array<string, array<string, string>> Cache načtených překladů [locale => [key => value]] */
    private static array $translations = [];

    /** @var string|null Cesta k adresáři s překlady */
    private static ?string $translationsDir = null;

    /**
     * Nastaví adresář s překlady.
     *
     * @param string $dir Absolutní cesta k adresáři
     */
    public static function setTranslationsDir(string $dir): void
    {
        self::$translationsDir = rtrim($dir, DIRECTORY_SEPARATOR);
    }

    /**
     * Nastaví aktuální jazyk.
     *
     * @param string $locale Kód jazyka (např. 'cs', 'en')
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    /**
     * Vrátí aktuální jazyk.
     *
     * @return string
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Přeloží klíč do aktuálního jazyka.
     *
     * @param string $key Klíč překladu (např. 'form.submit')
     * @param array $params Parametry pro interpolaci (např. ['name' => 'Jan'])
     * @param string|null $default Výchozí hodnota, pokud překlad neexistuje
     * @return string
     */
    public static function translate(string $key, array $params = [], ?string $default = null): string
    {
        self::ensureLoaded(self::$locale);

        $translation = self::$translations[self::$locale][$key] ?? $default ?? $key;

        return self::interpolate($translation, $params);
    }

    /**
     * Alias pro translate().
     *
     * @param string $key
     * @param array $params
     * @param string|null $default
     * @return string
     */
    public static function t(string $key, array $params = [], ?string $default = null): string
    {
        return self::translate($key, $params, $default);
    }

    /**
     * Zajistí, že překlady pro daný jazyk jsou načteny.
     *
     * @param string $locale
     */
    private static function ensureLoaded(string $locale): void
    {
        if (isset(self::$translations[$locale])) {
            return;
        }

        self::$translations[$locale] = [];

        if (self::$translationsDir === null) {
            return;
        }

        $filePath = self::$translationsDir . DIRECTORY_SEPARATOR . $locale . '.json';

        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (is_array($data)) {
            self::$translations[$locale] = self::flattenArray($data);
        }
    }

    /**
     * Zploští víceúrovňové pole na jednoúrovňové s tečkovou notací klíčů.
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    private static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Nahradí placeholdery v textu hodnotami z params.
     *
     * @param string $text Text s placeholdery (např. "Ahoj, {name}!")
     * @param array $params Asociativní pole parametrů
     * @return string
     */
    private static function interpolate(string $text, array $params): string
    {
        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)$value, $text);
        }

        return $text;
    }

    /**
     * Vymaže cache překladů (pro testování nebo reload).
     */
    public static function clearCache(): void
    {
        self::$translations = [];
    }

    /**
     * Starší metoda pro kompatibilitu - načtení z databáze.
     *
     * @deprecated Použijte translate() místo toho
     *
     * @param string $key
     * @param string $lang
     * @param string $default
     * @param string $group
     * @return string
     */
    public static function fetchTranslations(string $key, string $lang, string $default, string $group = "zoomdriver"): string
    {
        // Implementace pro databázové překlady (pro budoucí použití)
        // try {
        //     return $DB->getOne(
        //         $DB::table("cms_langs_translations")
        //             ->select("{$DB->escape($lang)}_text")
        //             ->where([
        //                 ['array_key', '=', $DB->escape($key)],
        //                 ['array_group', '=', $DB->escape($group)]
        //             ])
        //             ->toSql()
        //     )["{$lang}_text"] ?? $default;
        // } catch (\Exception $e) {
        //     return $default;
        // }

        return self::translate($key, [], $default);
    }
}
