<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Config\Exception;

/**
 * Výjimka pro chyby v konfiguraci PlatformBridge.
 *
 * Používá se pro:
 * - Chybějící konfigurační soubory
 * - Nevalidní JSON
 * - Chybějící povinné klíče
 * - Neplatné reference (layout_ref, block ref)
 * - Porušení pravidel validace
 */
class ConfigException extends \RuntimeException
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
     * Vytvoří výjimku pro chybějící soubor.
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
     * Vytvoří výjimku pro nevalidní JSON.
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
     * Vytvoří výjimku pro chybějící adresář.
     */
    public static function directoryNotFound(string $path): self
    {
        return new self(
            "Konfigurační adresář nenalezen: {$path}",
            self::CODE_DIRECTORY_NOT_FOUND
        );
    }

    /**
     * Vytvoří výjimku pro chybějící klíč.
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
     * Vytvoří výjimku pro neplatnou strukturu.
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
     * Vytvoří výjimku pro neplatnou referenci.
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
     * Vytvoří výjimku pro selhání validace.
     */
    public static function validationFailed(string $message): self
    {
        return new self($message, self::CODE_VALIDATION_FAILED);
    }

    public function getConfigFile(): ?string
    {
        return $this->configFile;
    }

    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }
}
