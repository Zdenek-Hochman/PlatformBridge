<?php

declare(strict_types=1);

namespace PlatformBridge\Translator;

/**
 * Enum překladových domén.
 *
 * Každá doména odpovídá jednomu JSON souboru v resources/lang/{locale}/.
 * Domény logicky oddělují překlady podle kontextu použití.
 */
enum Domain: string
{
    /** Chybové hlášky (HTTP, AI, validace) */
    case Errors = 'errors';

    /** UI texty (formuláře, notifikace, tlačítka) */
    case Ui = 'ui';

    /** API texty (odpovědi, status hlášky) */
    case Api = 'api';

    /** Texty bloků (labely, options, tooltipy formulářových prvků) */
    case Config = 'config';
}
