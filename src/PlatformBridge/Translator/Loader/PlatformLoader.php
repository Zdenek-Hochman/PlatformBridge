<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Domain;
use PlatformBridge\Translator\TranslationCatalog;
use PlatformBridge\Translator\Adapter\MysqliAdapter;
use PlatformBridge\Translator\Loader\TranslationLoaderInterface;

/**
 * Načítá překlady z databázového adaptéru.
 *
 * Slouží jako most mezi PlatformAdapterInterface (DB vrstva)
 * a TranslationCatalog. Překlady z DB přepisují (overridují) statické JSON soubory.
 *
 * V řetězci loaderů se typicky nachází za JsonFileLoader:
 *   1. JsonFileLoader (základ z resources/lang/)
 *   2. PlatformLoader (DB overrides) ← tento
 */
final class PlatformLoader implements TranslationLoaderInterface
{
    /**
     * @param MysqliAdapter $adapter DB adaptér
     * @param string $locale Kód locale (např. 'cs', 'en')
     */
    public function __construct(
        private readonly MysqliAdapter $adapter,
        private readonly string $locale,
    ) {}

    /**
     * Načte překlady z adaptéru do katalogu.
     *
     * Pokud adaptér nepodporuje překlady (NullAdapter), přeskočí se.
     * Překlady z DB se slučují do katalogu — přepisují stejné klíče ze JSON.
     *
     * @param TranslationCatalog $catalog Cílový katalog
     * @param Domain[] $domains Domény k načtení (prázdné = všechny)
     */
    public function load(TranslationCatalog $catalog, array $domains = []): void
    {
        if (!$this->adapter->supports()) {
            return;
        }

        $domainsToLoad = !empty($domains) ? $domains : Domain::cases();

        foreach ($domainsToLoad as $domain) {
            $messages = $this->adapter->fetch($this->locale, $domain);

            if (!empty($messages)) {
                $catalog->add($domain, $messages);
            }
        }
    }
}
