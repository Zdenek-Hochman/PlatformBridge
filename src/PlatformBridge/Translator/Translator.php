<?php

declare(strict_types=1);

namespace PlatformBridge\Translator;

use PlatformBridge\Translator\Loader\TranslationLoaderInterface;
use PlatformBridge\Translator\Loader\JsonFileLoader;

/**
 * Hlavní fasáda překladového systému PlatformBridge.
 *
 * Poskytuje jednoduché API pro překlad textů s podporou:
 * - Domén (errors, ui, api, config)
 * - Fallback hodnot
 * - Interpolace parametrů ({:param})
 * - Více zdrojů překladů (JSON soubory, DB adaptéry)
 *
 * Lifecycle:
 *   1. create() — vytvoří Translator s loadery
 *   2. t()      — přeloží klíč
 *   3. tp()     — přeloží klíč s parametry
 *
 * @example
 * ```php
 * $translator = Translator::create('cs', '/path/to/lang');
 * echo $translator->t('errors', 'http.400');                        // "Neplatný požadavek."
 * echo $translator->tp('errors', 'ai.timeout', ['seconds' => 30]); // "Požadavek vypršel po 30s"
 * ```
 */
final class Translator
{
    private TranslationCatalog $catalog;

    /** @var TranslationLoaderInterface[] Registrované loadery */
    private array $loaders = [];

    private bool $loaded = false;

    /**
     * @param string $locale Kód locale (např. 'cs', 'en')
     */
    public function __construct(
        private readonly string $locale,
    ) {
        $this->catalog = new TranslationCatalog($this->locale);
    }

    /**
     * Factory: Vytvoří Translator s JsonFileLoader.
     *
     * @param string $locale Kód locale
     * @param string $langPath Absolutní cesta k resources/lang
     * @param TranslationLoaderInterface|null $platformLoader Volitelný další loader (DB apod.)
     */
    public static function create(
        string $locale,
        string $langPath,
        ?TranslationLoaderInterface $platformLoader = null,
    ): self {
        $translator = new self($locale);

        // 1. Statické JSON soubory (základ)
        // $translator->addLoader(new JsonFileLoader($langPath, $locale));

        // 2. DB / platformový loader (overrides)
        if ($platformLoader !== null) {
            $translator->addLoader($platformLoader);
        }

        return $translator;
    }

    /**
     * Přidá loader do řetězce.
     * Loadery se vykonávají v pořadí přidání — pozdější přepisují dřívější.
     */
    public function addLoader(TranslationLoaderInterface $loader): self
    {
        $this->loaders[] = $loader;
        $this->loaded = false;
        return $this;
    }

    /**
     * Přeloží klíč.
     *
     * @param string $domain Doména překladu ('errors', 'ui', 'api', 'config')
     * @param string $key Klíč v tečkové notaci ('http.400', 'form.required')
     * @param array<string, string|int> $params Parametry pro interpolaci
     * @param string|null $fallback Výchozí text pokud překlad neexistuje
     * @return string Přeložený text
     */
    public function t(string $domain, string $key, array $params = [], ?string $fallback = null): string
    {
        $this->ensureLoaded();

        $domainEnum = Domain::tryFrom($domain);
        if ($domainEnum === null) {
            return $fallback ?? $key;
        }

        $message = $this->catalog->get($domainEnum, $key);

        if ($message === null) {
            return $fallback ?? $key;
        }

        if (!empty($params)) {
            $message = $this->interpolate($message, $params);
        }

        return $message;
    }

    /**
     * Přeloží klíč s interpolací parametrů (shortcut).
     *
     * @param string $domain Doména překladu
     * @param string $key Klíč v tečkové notaci
     * @param array<string, string|int> $params Parametry — {:param} se nahradí hodnotou
     * @param string|null $fallback Výchozí text
     * @return string Přeložený a interpolovaný text
     */
    // public function tp(string $domain, string $key, array $params, ?string $fallback = null): string
    // {
    //     return $this->t($domain, $key, $params, $fallback);
    // }

    /**
     * Vrátí VariableResolver svázaný s tímto Translatorem.
     * Pro nahrazování {$domain.key} proměnných v textech.
     */
    public function getVariableResolver(): VariableResolver
    {
        return new VariableResolver($this);
    }

    /**
     * Vrátí TranslationCatalog (pro export endpoint apod.).
     */
    public function getCatalog(): TranslationCatalog
    {
        $this->ensureLoaded();
        return $this->catalog;
    }

    /**
     * Vrátí aktuální locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Přeloží klíče v poli pomocí překladů z dané domény.
     *
     * Typické použití: překlad klíčů v odpovědi z AI API před vykreslením do šablony.
     * API vrátí pole jako ["Subject1" => "...", "Subject2" => "..."],
     * klíče se přeloží podle domény (např. "api" nebo "blocks").
     *
     * Pokud překlad pro klíč neexistuje, použije se původní klíč jako fallback.
     * Rekurzivně zpracovává vnořené pole.
     *
     * @param array $data Vstupní pole s klíči k překladu
     * @param string $domain Doména překladů (např. 'api', 'blocks')
     * @param string $keyPrefix Prefix pro vnořené klíče (pro rekurzi)
     * @return array Pole s přeloženými klíči
     *
     * @example
     * ```php
     * // API vrátí:
     * $response = ['Subject1' => 'Hello', 'Subject2' => 'World'];
     * // Po překladu (doména 'api', cs locale):
     * $translated = $translator->translateKeys($response, 'api');
     * // ['Předmět 1' => 'Hello', 'Předmět 2' => 'World']
     * ```
     */
    // public function translateKeys(array $data, string $domain = 'api', string $keyPrefix = ''): array
    // {
        // $result = [];

        // foreach ($data as $key => $value) {
        //     $lookupKey = $keyPrefix !== '' ? $keyPrefix . '.' . $key : (string) $key;
        //     $translatedKey = $this->t($domain, $lookupKey, [], (string) $key);

        //     if (is_array($value)) {
        //         $result[$translatedKey] = $this->translateKeys($value, $domain, $lookupKey);
        //     } else {
        //         $result[$translatedKey] = $value;
        //     }
        // }

        // return $result;
    // }

    /**
     * Zajistí, že jsou překlady načteny (lazy loading).
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach ($this->loaders as $loader) {
            $loader->load($this->catalog);
        }

        $this->loaded = true;
    }

    /**
     * Nahradí {:param} placeholdery hodnotami.
     *
     * @param string $message Text s placeholdery
     * @param array<string, string|int> $params Parametry
     * @return string Interpolovaný text
     */
    private function interpolate(string $message, array $params): string
    {
        foreach ($params as $param => $value) {
            $message = str_replace("{:{$param}}", (string) $value, $message);
        }

        return $message;
    }
}
