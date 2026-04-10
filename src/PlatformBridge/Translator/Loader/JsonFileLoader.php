<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Domain;
use PlatformBridge\Translator\TranslationCatalog;

/**
 * Načítá překlady ze statických JSON souborů.
 *
 * Očekávaná struktura:
 *   {langPath}/{locale}/errors.json
 *   {langPath}/{locale}/ui.json
 *   {langPath}/{locale}/api.json
 *   {langPath}/{locale}/blocks.json
 *
 * Každý JSON soubor je flat mapa: { "klíč": "hodnota", ... }
 * Klíče mohou být v tečkové notaci: { "http.400": "Neplatný požadavek." }
 */
final class JsonFileLoader implements TranslationLoaderInterface
{
    /**
     * @param string $langPath Absolutní cesta k adresáři lang/ (např. resources/lang)
     * @param string $locale Kód locale (např. 'cs', 'en')
     */
    public function __construct(
        private readonly string $langPath,
        private readonly string $locale,
    ) {}

    /**
     * Načte překlady z JSON souborů do katalogu.
     *
     * @param TranslationCatalog $catalog Cílový katalog
     * @param Domain[] $domains Domény k načtení (prázdné = všechny)
     */
    public function load(TranslationCatalog $catalog, array $domains = []): void
    {
    //     $domainsToLoad = !empty($domains) ? $domains : Domain::cases();

    //     foreach ($domainsToLoad as $domain) {
    //         $messages = $this->loadDomainFile($domain);
    //         if (!empty($messages)) {
    //             $catalog->add($domain, $messages);
    //         }
    //     }
    }

    /**
     * Načte jeden JSON soubor pro doménu.
     *
     * @param Domain $domain Překladová doména
     * @return array<string, string> Klíč → text (prázdné pole pokud soubor neexistuje)
     */
    // private function loadDomainFile(Domain $domain): array
    // {
    //     $file = $this->buildFilePath($domain);

    //     if (!file_exists($file)) {
    //         return [];
    //     }

    //     $raw = file_get_contents($file);
    //     if ($raw === false) {
    //         return [];
    //     }

    //     try {
    //         $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    //     } catch (\JsonException) {
    //         return [];
    //     }

    //     if (!is_array($data)) {
    //         return [];
    //     }

    //     // Flatten vnořených struktur (pokud JSON obsahuje zanořené objekty)
    //     return $this->flatten($data);
    // }

    /**
     * Sestaví cestu k JSON souboru.
     * Např. /resources/lang/cs/errors.json
     */
    // private function buildFilePath(Domain $domain): string
    // {
    //     return $this->langPath
    //         . DIRECTORY_SEPARATOR . $this->locale
    //         . DIRECTORY_SEPARATOR . $domain->value . '.json';
    // }

    /**
     * Zploští vnořený JSON do flat mapy s tečkovou notací.
     *
     * Vstup:  { "http": { "400": "Bad request", "500": "Server error" } }
     * Výstup: { "http.400": "Bad request", "http.500": "Server error" }
     *
     * Pokud je hodnota string, zůstane jak je.
     *
     * @param array $data Vnořená nebo flat data
     * @param string $prefix Aktuální prefix pro rekurzi
     * @return array<string, string> Flat mapa
     */
    // private function flatten(array $data, string $prefix = ''): array
    // {
    //     $result = [];

    //     foreach ($data as $key => $value) {
    //         $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;

    //         if (is_array($value)) {
    //             $result = array_merge($result, $this->flatten($value, $fullKey));
    //         } else {
    //             $result[$fullKey] = (string) $value;
    //         }
    //     }

    //     return $result;
    // }
}
