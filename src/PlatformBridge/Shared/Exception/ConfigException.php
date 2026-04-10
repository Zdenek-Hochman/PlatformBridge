<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Exception;

use PlatformBridge\Error\RenderableException;

/**
 * Výjimka pro chyby v konfiguraci PlatformBridge.
 *
 * Používá se výhradně pro doménové konfigurační chyby:
 * - Chybějící povinné klíče
 * - Neplatná struktura dat
 * - Neplatné reference (layout_ref, block ref)
 * - Porušení pravidel validace
 *
 * Pro chyby souborového systému viz {@see FileException}.
 * Pro chyby JSON dekódování/kódování viz {@see JsonException}.
 *
 * Implementuje {@see RenderableException} pro zobrazení cesty k souboru a klíče.
 */
class ConfigException extends \RuntimeException implements RenderableException
{
    public const CODE_INVALID_STRUCTURE = 1003;
    public const CODE_MISSING_KEY = 1004;
    public const CODE_INVALID_REFERENCE = 1005;
    public const CODE_VALIDATION_FAILED = 1006;

    private ?string $configFile = null;
    private ?string $configKey = null;

	/**
	 * Vytvoří výjimku pro chybějící povinný klíč v konfiguračním souboru.
	 *
	 * @param string $file Název nebo cesta k souboru, kde klíč chybí
	 * @param string $key Název chybějícího klíče
	 * @param string|null $context Kontext (např. sekce, blok), kde klíč chybí, nebo null
	 *
	 * @return self Instance výjimky ConfigException s kódem CODE_MISSING_KEY
	 */
    public static function missingKey(string $file, string $key, ?string $context = null): self
    {
        $message = "V souboru \"{$file}\" chybí povinný klíč \"{$key}\"";
        if ($context !== null) {
            $message .= " v kontextu: {$context}";
        }

        $exception = new self($message, self::CODE_MISSING_KEY);
        $exception->configFile = $file;
        $exception->configKey = $key;
        return $exception;
    }

	/**
	 * Vytvoří výjimku pro neplatnou strukturu v konfiguračním souboru.
	 *
	 * @param string $file Název nebo cesta k souboru, kde je neplatná struktura
	 * @param string $message Detailní popis chyby struktury
	 *
	 * @return self Instance výjimky ConfigException s kódem CODE_INVALID_STRUCTURE
	 */
    public static function invalidStructure(string $file, string $message): self
    {
        $exception = new self(
            "Neplatná struktura v \"{$file}\": {$message}",
            self::CODE_INVALID_STRUCTURE
        );
        $exception->configFile = $file;
        return $exception;
    }

	/**
	 * Vytvoří výjimku pro neplatnou referenci v konfiguraci (např. layout_ref, block ref).
	 *
	 * @param string $type Typ reference (např. 'layout', 'block')
	 * @param string $ref Hodnota reference (např. id layoutu/bloku)
	 * @param string|null $context Kontext, kde reference selhala (např. sekce, generátor), nebo null
	 *
	 * @return self Instance výjimky ConfigException s kódem CODE_INVALID_REFERENCE
	 */
    public static function invalidReference(string $type, string $ref, ?string $context = null): self
    {
        $message = "Neplatná reference na {$type}: \"{$ref}\"";
        if ($context !== null) {
            $message .= " ({$context})";
        }

        return new self($message, self::CODE_INVALID_REFERENCE);
    }


	/**
	 * Vytvoří výjimku pro selhání validace v konfiguraci.
	 *
	 * @param string $message Detailní popis chyby validace
	 * @return self Instance výjimky ConfigException s kódem CODE_VALIDATION_FAILED
	 */
    public static function validationFailed(string $message): self
    {
        return new self($message, self::CODE_VALIDATION_FAILED);
    }

	/**
	 * Vrací titulek chyby pro zobrazení uživateli podle typu chyby v konfiguraci.
	 *
	 * @return string Titulek chyby (např. 'Konfigurace – chybějící klíč', 'Konfigurace – validace selhala')
	 */
    public function getTitle(): string
    {
        return match ($this->code) {
            self::CODE_MISSING_KEY        => 'Konfigurace – chybějící klíč',
            self::CODE_INVALID_REFERENCE  => 'Konfigurace – neplatná reference',
            self::CODE_INVALID_STRUCTURE  => 'Konfigurace – neplatná struktura',
            self::CODE_VALIDATION_FAILED  => 'Konfigurace – validace selhala',
            default                       => 'Chyba konfigurace',
        };
    }

    /**
     * Vrací HTTP status kód odpovídající této výjimce.
     *
     * @return int HTTP status kód (např. 500 pro interní chybu serveru)
     */
    public function getHttpStatusCode(): int
    {
        return 500;
    }

    /**
     * Vrací nápovědu pro řešení chyby na základě kódu výjimky.
     *
     * @return string|null Text nápovědy pro uživatele nebo null, pokud není nápověda dostupná.
     */
    public function getHint(): ?string
    {
        return match ($this->code) {
            self::CODE_MISSING_KEY
                => 'Doplň chybějící klíč do konfiguračního souboru.',
            self::CODE_INVALID_REFERENCE
                => 'Ověř, že odkazovaný layout/block existuje v konfiguraci.',
            default => null,
        };
    }

    /**
     * Vrací kontext chyby pro vykreslení, obsahující informace o konfiguračním souboru a klíči.
     *
     * @return array Asociativní pole s klíči 'Konfigurační soubor' a/nebo 'Klíč', pokud jsou dostupné.
     */
	//TODO: Přepsat klíče na proměnné. (Text se vypisuje jako nadpis)
    public function getRenderContext(): array
    {
        $ctx = [];
        if ($this->configFile !== null) {
            $ctx['Konfigurační soubor'] = $this->configFile;
        }
        if ($this->configKey !== null) {
            $ctx['Klíč'] = $this->configKey;
        }
        return $ctx;
    }
}
