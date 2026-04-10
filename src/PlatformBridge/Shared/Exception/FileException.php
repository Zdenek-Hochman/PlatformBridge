<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Exception;

/**
 * Výjimka pro obecné chyby při práci se soubory a adresáři.
 *
 * Používá se pro:
 * - Chybějící soubory nebo adresáře
 * - Nečitelné / nepřístupné soubory
 * - Překročení limitu velikosti souboru
 * - Selhání při zápisu nebo vytváření adresáře
 *
 * Tato výjimka je doménově nezávislá – lze ji použít v jakémkoli kontextu
 * (AI, Config, Template, ...) aniž by vznikla závislost na konkrétním modulu.
 */
class FileException extends \RuntimeException
{
	public const CODE_NOT_FOUND       = 4001;
	public const CODE_UNREADABLE      = 4002;
	public const CODE_EMPTY           = 4003;
	public const CODE_SIZE_EXCEEDED   = 4004;
	public const CODE_WRITE_FAILED    = 4005;
	public const CODE_DIR_NOT_FOUND   = 4006;
	public const CODE_DIR_CREATE_FAIL = 4007;

	private string $filePath;

	public function __construct(string $message, int $code, string $filePath, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->filePath = $filePath;
	}

	/**
	 * Cesta k souboru/adresáři, který způsobil chybu.
	 */
	public function getFilePath(): string
	{
		return $this->filePath;
	}

	/**
	 * Soubor nebyl nalezen.
	 */
	public static function notFound(string $path): self
	{
		return new self(
			"Soubor nenalezen: {$path}",
			self::CODE_NOT_FOUND,
			$path,
		);
	}

	/**
	 * Soubor existuje, ale nelze ho přečíst.
	 */
	public static function unreadable(string $path): self
	{
		return new self(
			"Soubor nelze přečíst: {$path}",
			self::CODE_UNREADABLE,
			$path,
		);
	}

	/**
	 * Soubor je prázdný nebo obsahuje jen prázdné znaky.
	 */
	public static function empty(string $path): self
	{
		return new self(
			basename($path) . ' je prázdný nebo nečitelný.',
			self::CODE_EMPTY,
			$path,
		);
	}

	/**
	 * Soubor překročil maximální povolenou velikost.
	 *
	 * @param string $path Cesta k souboru
	 * @param int $maxSizeBytes Maximální povolená velikost v bajtech
	 * @param int $actualSizeBytes Skutečná velikost souboru v bajtech
	 */
	public static function sizeExceeded(string $path, int $maxSizeBytes, int $actualSizeBytes): self
	{
		return new self(
			sprintf(
				'%s překračuje maximální povolenou velikost %d KB (aktuální: %d KB).',
				basename($path),
				(int) ($maxSizeBytes / 1024),
				(int) ($actualSizeBytes / 1024),
			),
			self::CODE_SIZE_EXCEEDED,
			$path,
		);
	}

	/**
	 * Zápis do souboru selhal.
	 */
	public static function writeFailed(string $path): self
	{
		return new self(
			"Zápis do souboru selhal: {$path}",
			self::CODE_WRITE_FAILED,
			$path,
		);
	}

	/**
	 * Adresář nebyl nalezen.
	 */
	public static function directoryNotFound(string $path): self
	{
		return new self(
			"Adresář nenalezen: {$path}",
			self::CODE_DIR_NOT_FOUND,
			$path,
		);
	}

	/**
	 * Vytvoření adresáře selhalo.
	 */
	public static function directoryCreateFailed(string $path): self
	{
		return new self(
			"Nelze vytvořit adresář: {$path}",
			self::CODE_DIR_CREATE_FAIL,
			$path,
		);
	}
}
