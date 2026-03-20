<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config\Exception;

use Zoom\PlatformBridge\Error\RenderableException;

/**
 * Výjimka pro chyby v konfiguraci PlatformBridge.
 *
 * Používá se pro:
 * - Chybějící konfigurační soubory
 * - Nevalidní JSON
 * - Chybějící povinné klíče
 * - Neplatné reference (layout_ref, block ref)
 * - Porušení pravidel validace
 *
 * Implementuje {@see RenderableException} pro zobrazení cesty k souboru a klíče.
 */
class ConfigException extends \RuntimeException implements RenderableException
{
    public const CODE_FILE_NOT_FOUND = 1001;
    public const CODE_INVALID_JSON = 1002;
    public const CODE_INVALID_STRUCTURE = 1003;
    public const CODE_MISSING_KEY = 1004;
    public const CODE_INVALID_REFERENCE = 1005;
    public const CODE_VALIDATION_FAILED = 1006;
    public const CODE_DIRECTORY_NOT_FOUND = 1007;

    private ?string $configFile = null;
    private ?string $configKey = null;

	/**
	 * Vytvoří výjimku pro chybějící konfigurační soubor.
	 *
	 * @param string $path Cesta k chybějícímu souboru
	 *
	 * @return self Instance výjimky ConfigException s kódem CODE_FILE_NOT_FOUND
	 */
    public static function fileNotFound(string $path): self
    {
        $exception = new self(
            "Konfigurační soubor nenalezen: {$path}",
            self::CODE_FILE_NOT_FOUND
        );
        $exception->configFile = $path;
        return $exception;
    }

	/**
	 * Vytvoří výjimku pro nevalidní JSON v konfiguračním souboru.
	 *
	 * @param string $path Cesta k souboru s nevalidním JSON
	 * @param string $error Chybová zpráva z parsování JSON
	 *
	 * @return self Instance výjimky ConfigException s kódem CODE_INVALID_JSON
	 */
    public static function invalidJson(string $path, string $error): self
    {
        $exception = new self(
            "Nevalidní JSON v souboru {$path}: {$error}",
            self::CODE_INVALID_JSON
        );
        $exception->configFile = $path;
        return $exception;
    }

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
	 * @return string Titulek chyby (např. 'Konfigurace – soubor nenalezen', 'Konfigurace – validace selhala')
	 */
    public function getTitle(): string
    {
        return match ($this->code) {
            self::CODE_FILE_NOT_FOUND     => 'Konfigurace – soubor nenalezen',
            self::CODE_DIRECTORY_NOT_FOUND => 'Konfigurace – adresář nenalezen',
            self::CODE_INVALID_JSON       => 'Konfigurace – nevalidní JSON',
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
            self::CODE_FILE_NOT_FOUND, self::CODE_DIRECTORY_NOT_FOUND
                => 'Ověř, že konfigurační soubory existují a cesty v konfiguraci jsou správné.',
            self::CODE_INVALID_JSON
                => 'Zkontroluj JSON syntaxi konfiguračního souboru (např. přes jsonlint).',
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
