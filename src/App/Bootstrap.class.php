<?php

declare(strict_types=1);

namespace App;

use Parser\Resolver;
use Error\ErrorHandler;
use Translator\Translator;

final class Bootstrap
{
    public static function init(): void
    {
		$handler = new ErrorHandler(true); // true = zobrazovat full trace (dev). do produkce nastav na false
		$handler->register();

        // Inicializace překladů
        Translator::setTranslationsDir(__DIR__ . '/../../config/translations/');
        Translator::setLocale('cs');

        Resolver::load(__DIR__ . '/../../config/json/');
    }

    /**
     * Nastaví jazyk aplikace.
     *
     * @param string $locale Kód jazyka (např. 'cs', 'en')
     */
    public static function setLocale(string $locale): void
    {
        Translator::setLocale($locale);
    }
}