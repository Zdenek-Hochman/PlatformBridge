<?php

declare(strict_types=1);

namespace PlatformBridge\Security\Exception;

use PlatformBridge\Error\RenderableException;

/**
 * Výjimka pro bezpečnostní chyby (neplatný podpis, expirace, atd.).
 *
 * Implementuje {@see RenderableException} pro specifické zobrazení bezpečnostních chyb.
 */
class SecurityException extends \Exception implements RenderableException
{
    /**
     * Kód chyby pro neplatný podpis požadavku (HMAC).
     */
    public const CODE_INVALID_SIGNATURE = 2001;

    /**
     * Kód chyby pro expirovaný (neplatný) token.
     */
    public const CODE_EXPIRED_TOKEN = 2002;

    /**
     * Kód chyby pro chybějící bezpečnostní token v požadavku.
     */
    public const CODE_MISSING_TOKEN = 2003;

	/**
	 * Vytvoří novou bezpečnostní výjimku.
	 *
	 * @param string $message Popis chyby.
	 * @param int $code Kód chyby (viz konstanty).
	 * @param \Throwable|null $previous Předchozí výjimka pro řetězení.
	 */
	public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}

    /**
     * Vrací titulek chyby podle typu bezpečnostní chyby.
     *
     * Titulek slouží pro uživatelské zobrazení v UI nebo logování.
     * Výběr titulku je určen hodnotou kódu chyby (property $code).
     *
     * @return string Titulek chyby pro uživatele (např. "Bezpečnost – chybějící token").
     */
    public function getTitle(): string
    {
        return match ($this->code) {
            self::CODE_MISSING_TOKEN => 'Bezpečnost – chybějící token',
            self::CODE_INVALID_SIGNATURE => 'Bezpečnost – neplatný podpis',
            self::CODE_EXPIRED_TOKEN => 'Bezpečnost – token vypršel',
            default => 'Bezpečnostní chyba',
        };
    }

	/**
	 * Vrací odpovídající HTTP status kód pro danou bezpečnostní chybu.
	 * Například chybějící token vrací 401 (neautorizováno), ostatní chyby 403 (zakázáno).
	 *
	 * @return int HTTP status kód (401 nebo 403).
	 */
    public function getHttpStatusCode(): int
    {
        return match ($this->code) {
            self::CODE_MISSING_TOKEN => 401,
            self::CODE_INVALID_SIGNATURE => 403,
            self::CODE_EXPIRED_TOKEN => 403,
            default => 403,
        };
    }

	/**
	 * Vrací nápovědu pro řešení konkrétní bezpečnostní chyby.
	 * Například doporučení pro obnovu tokenu nebo kontrolu podpisu.
	 *
	 * @return string|null Textová nápověda nebo null pokud není k dispozici.
	 */
    public function getHint(): ?string
    {
        return match ($this->code) {
			self::CODE_MISSING_TOKEN => 'Požadavek neobsahuje bezpečnostní token.',
			self::CODE_INVALID_SIGNATURE => 'Podpis požadavku neodpovídá – ověř HMAC klíč a pořadí parametrů.',
			self::CODE_EXPIRED_TOKEN => 'Platnost tokenu vypršela – vygeneruj nový podepsaný odkaz.',
            default => null,
        };
    }

	/**
	 * Vrací kontext pro renderování chyby (pro šablony, logování apod.).
	 * Zde prázdné pole, lze rozšířit podle potřeby.
	 *
	 * @return array Kontext pro renderování chyby.
	 */
    public function getRenderContext(): array
    {
        return [];
    }
}
