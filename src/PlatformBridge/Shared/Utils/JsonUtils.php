<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Utils;

use PlatformBridge\Security\JsonGuard;
use PlatformBridge\Shared\Exception\FileException;
use PlatformBridge\Shared\Exception\JsonException;

/**
 * Centrální utilita pro práci s JSON – dekódování, kódování, čtení souborů.
 *
 * Eliminuje duplicitní JSON vzory napříč projektem:
 * - json_decode s try/catch a validací typu
 * - json_encode s konzistentními flagy
 * - čtení JSON souborů s guard stripem
 *
 * @throws JsonException Pro chyby dekódování/kódování
 * @throws FileException Pro chyby čtení souborů (delegováno na {@see FileUtils})
 */
final class JsonUtils
{
	/** Výchozí maximální velikost JSON souboru (256 KB). */
	public const DEFAULT_MAX_FILE_SIZE = 256 * 1024;

	/** Standardní flagy pro json_encode. */
	public const ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

	/** Flagy pro čitelný (pretty) JSON výstup. */
	public const ENCODE_FLAGS_PRETTY = self::ENCODE_FLAGS | JSON_PRETTY_PRINT;

	/**
	 * Dekóduje JSON řetězec do pole.
	 *
	 * @param string $json JSON řetězec
	 * @param string|null $filePath Volitelná cesta k souboru (pro chybové hlášky)
	 *
	 * @return array Dekódovaná data
	 *
	 * @throws JsonException Pokud JSON je nevalidní nebo výsledek není pole
	 */
	public static function decode(string $json, ?string $filePath = null): array
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw JsonException::decodeFailed($e->getMessage(), $filePath, $e);
		}

		if (!is_array($data)) {
			throw JsonException::invalidType('array', gettype($data), $filePath);
		}

		return $data;
	}

	/**
	 * Dekóduje JSON řetězec a vrátí libovolný typ (nejen pole).
	 *
	 * @param string $json JSON řetězec
	 * @param string|null $filePath Volitelná cesta k souboru (pro chybové hlášky)
	 *
	 * @return mixed Dekódovaná data
	 *
	 * @throws JsonException Pokud JSON je nevalidní
	 */
	public static function decodeAny(string $json, ?string $filePath = null): mixed
	{
		try {
			return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw JsonException::decodeFailed($e->getMessage(), $filePath, $e);
		}
	}

	/**
	 * Zakóduje data do JSON řetězce s konzistentními flagy.
	 *
	 * @param mixed $data Data ke kódování
	 * @param int $flags JSON flagy (výchozí: ENCODE_FLAGS)
	 *
	 * @return string JSON řetězec
	 *
	 * @throws JsonException Pokud kódování selže
	 */
	public static function encode(mixed $data, int $flags = self::ENCODE_FLAGS): string
	{
		try {
			return json_encode($data, $flags | JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw JsonException::encodeFailed($e->getMessage());
		}
	}

	/**
	 * Zakóduje data do čitelného (pretty-printed) JSON.
	 *
	 * @param mixed $data Data ke kódování
	 *
	 * @return string Formátovaný JSON řetězec
	 *
	 * @throws JsonException Pokud kódování selže
	 */
	public static function encodePretty(mixed $data): string
	{
		return self::encode($data, self::ENCODE_FLAGS_PRETTY);
	}

	/**
	 * Načte JSON soubor, volitelně stripne guard, dekóduje do pole.
	 *
	 * @param string $path Absolutní cesta k JSON souboru
	 * @param bool $stripGuard Zda odstranit PHP guard header ({@see JsonGuard})
	 * @param int|null $maxSize Maximální velikost souboru v bajtech (null = DEFAULT_MAX_FILE_SIZE)
	 *
	 * @return array Dekódovaná JSON data
	 *
	 * @throws FileException Pokud soubor neexistuje, je nečitelný nebo příliš velký
	 * @throws JsonException Pokud JSON je nevalidní nebo výsledek není pole
	 */
	public static function readFile(string $path, bool $stripGuard = true, ?int $maxSize = null): array
	{
		$raw = FileUtils::readFileNonEmpty($path, $maxSize ?? self::DEFAULT_MAX_FILE_SIZE);
		$json = $stripGuard ? JsonGuard::strip($raw) : $raw;

		return self::decode($json, $path);
	}
}