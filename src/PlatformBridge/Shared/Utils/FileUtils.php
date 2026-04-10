<?php

declare(strict_types=1);

namespace PlatformBridge\Shared\Utils;

use PlatformBridge\Shared\Exception\FileException;

/**
 * Centrální utilita pro běžné souborové operace.
 *
 * Eliminuje duplicitní vzory:
 * - file_exists + file_get_contents + validace
 * - is_dir + mkdir
 * - kontrola velikosti souboru
 *
 * Všechny metody vyhazují {@see FileException}, která je doménově nezávislá.
 */
final class FileUtils
{
	/**
	 * Načte obsah souboru. Ověří existenci, čitelnost a volitelně velikost.
	 *
	 * @param string $path Absolutní cesta k souboru
	 * @param int|null $maxSizeBytes Maximální povolená velikost v bajtech (null = bez limitu)
	 *
	 * @return string Obsah souboru
	 *
	 * @throws FileException Pokud soubor neexistuje, je nečitelný, prázdný nebo příliš velký
	 */
	public static function readFile(string $path, ?int $maxSizeBytes = null): string
	{
		if (!file_exists($path)) {
			throw FileException::notFound($path);
		}

		if (!is_readable($path)) {
			throw FileException::unreadable($path);
		}

		if ($maxSizeBytes !== null) {
			$fileSize = filesize($path);
			if ($fileSize === false) {
				throw FileException::unreadable($path);
			}
			if ($fileSize > $maxSizeBytes) {
				throw FileException::sizeExceeded($path, $maxSizeBytes, $fileSize);
			}
		}

		$content = file_get_contents($path);
		if ($content === false) {
			throw FileException::unreadable($path);
		}

		return $content;
	}

	/**
	 * Načte obsah souboru a ověří, že není prázdný.
	 *
	 * @param string $path Absolutní cesta k souboru
	 * @param int|null $maxSizeBytes Maximální povolená velikost v bajtech (null = bez limitu)
	 *
	 * @return string Neprázdný obsah souboru
	 *
	 * @throws FileException Pokud soubor neexistuje, je nečitelný, prázdný nebo příliš velký
	 */
	public static function readFileNonEmpty(string $path, ?int $maxSizeBytes = null): string
	{
		$content = self::readFile($path, $maxSizeBytes);

		if (trim($content) === '') {
			throw FileException::empty($path);
		}

		return $content;
	}

	/**
	 * Zajistí, že adresář existuje. Pokud neexistuje, vytvoří ho (rekurzivně).
	 *
	 * @param string $path Cesta k adresáři
	 * @param int $permissions Oprávnění pro nový adresář (výchozí 0755)
	 *
	 * @throws FileException Pokud se nepodaří adresář vytvořit
	 */
	public static function ensureDirectory(string $path, int $permissions = 0755): void
	{
		if (is_dir($path)) {
			return;
		}

		if (!@mkdir($path, $permissions, true) && !is_dir($path)) {
			throw FileException::directoryCreateFailed($path);
		}
	}

	/**
	 * Zapíše obsah do souboru. Pokud adresář neexistuje, vytvoří ho.
	 *
	 * @param string $path Cesta k souboru
	 * @param string $content Obsah pro zápis
	 * @param bool $ensureDir Zda vytvořit adresář, pokud neexistuje
	 *
	 * @throws FileException Pokud zápis selže
	 */
	public static function writeFile(string $path, string $content, bool $ensureDir = true): void
	{
		if ($ensureDir) {
			self::ensureDirectory(dirname($path));
		}

		if (file_put_contents($path, $content) === false) {
			throw FileException::writeFailed($path);
		}
	}

	/**
	 * Ověří, že soubor existuje.
	 *
	 * @throws FileException Pokud soubor neexistuje
	 */
	public static function assertExists(string $path): void
	{
		if (!file_exists($path)) {
			throw FileException::notFound($path);
		}
	}

	/**
	 * Ověří, že adresář existuje.
	 *
	 * @throws FileException Pokud adresář neexistuje
	 */
	public static function assertDirectoryExists(string $path): void
	{
		if (!is_dir($path)) {
			throw FileException::directoryNotFound($path);
		}
	}
}
