<?php

declare(strict_types=1);

namespace PlatformBridge\Translator\Loader;

use PlatformBridge\Translator\Domain;
use PlatformBridge\Translator\TranslationCatalog;

/**
 * Interface pro loadery překladů.
 *
 * Loadery zodpovídají za načtení překladů z konkrétního zdroje
 * (JSON soubory, databáze, cache, atd.) do TranslationCatalog.
 */
interface TranslationLoaderInterface
{
    /**
     * Načte překlady pro zadané domény do katalogu.
     *
     * @param TranslationCatalog $catalog Katalog, do kterého se překlady přidají
     * @param Domain[] $domains Domény k načtení (prázdné = všechny)
     */
    public function load(TranslationCatalog $catalog, array $domains = []): void;
}
