<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Security;

/**
 * Třída JsonGuard poskytuje bezpečnostní mechanismy pro ochranu citlivých JSON konfiguračních souborů
 * před přímým přístupem přes webový server. Umožňuje:
 *
 * - Obalit JSON obsah PHP "exit guardem", který zabrání zobrazení obsahu přes prohlížeč (odesláním HTTP 403).
 * - Detekovat a odstranit ochrannou PHP hlavičku z obsahu (pro zpětnou kompatibilitu a práci s čistým JSONem).
 * - Bezpečně číst a zapisovat chráněné konfigurační soubory.
 * - Konvertovat běžné .json soubory na chráněné .json.php soubory.
 * - Generovat a vytvářet ochranné index.php soubory v adresářích, aby nebylo možné procházet adresářovou strukturu.
 * - Zajistit, že všechny konfigurační soubory mají správnou ochrannou příponu.
 *
 * Typické použití je v rámci frameworku PlatformBridge pro ochranu konfigurací, které by neměly být veřejně přístupné.
 *
 * Všechny metody jsou statické, třída není určena k instancování.
 */
final class JsonGuard
{
    /** Pripona pro chranene JSON soubory */
    public const PROTECTED_EXTENSION = '.json.php';

    /** PHP oteviraci znacka jako bezpecny string (vyhnuti se tokenizer problemum) */
    private const PHP_OPEN = '<' . '?php';

    /** PHP uzaviraci znacka jako bezpecny string */
    private const PHP_CLOSE = '?' . '>';

	/**
	 * Vrátí ochrannou PHP hlavičku pro konfigurační soubor.
	 *
	 * Generuje PHP kód, který zabrání přímému přístupu ke konfiguračnímu souboru
	 * přes webový prohlížeč. Při pokusu o načtení souboru dojde k odeslání HTTP 403
	 * a okamžitému ukončení skriptu.
	 *
	 * Tato hlavička je určena k vložení na začátek souboru obsahujícího citlivá data
	 * (např. JSON konfiguraci), která následují až za uzavírací PHP značkou.
	 *
	 * @return string PHP kód ochranné hlavičky
	 */
    private static function guardHeader(): string
    {
        return self::PHP_OPEN . "\n"
            . "/*\n"
            . " * PlatformBridge - Chraneny konfiguracni soubor.\n"
            . " * PHP hlavicka brani zobrazeni obsahu pres webovy prohlizec.\n"
            . " * NEODSTRANUJTE tuto PHP sekci - slouzi jako bezpecnostni ochrana.\n"
            . " * Editujte pouze JSON data pod uzaviraci PHP znackou.\n"
            . " */\n"
            . "header('HTTP/1.1 403 Forbidden');\n"
            . "exit;\n"
            . self::PHP_CLOSE;
    }

    /**
     * Obali JSON obsah PHP exit guardem.
     *
     * @param string $jsonContent Cisty JSON obsah
     * @return string Chraneny obsah (PHP guard + JSON)
     */
    public static function protect(string $jsonContent): string
    {
        return self::guardHeader() . "\n" . $jsonContent;
    }

    /**
     * Odstrani PHP exit guard a vrati cisty JSON obsah.
     * Pokud guard neni pritomen, vrati obsah beze zmeny (zpetna kompatibilita).
     *
     * @param string $content Obsah souboru (mozna s PHP guardem)
     * @return string Cisty JSON obsah
     */
	public static function strip(string $content): string
	{
		if (!self::isProtected($content)) {
			return $content;
		}

		$closePos = strrpos($content, self::PHP_CLOSE);

		if ($closePos === false) {
			return $content;
		}

		$json = substr($content, $closePos + strlen(self::PHP_CLOSE));
		return ltrim($json);
	}

	/**
	 * Ověří, zda obsah obsahuje ochrannou PHP hlavičku.
	 *
	 * Kontroluje, zda obsah (po odstranění počátečních whitespace znaků)
	 * začíná PHP otevírací značkou, což indikuje přítomnost ochranné hlavičky.
	 *
	 * @param string $content Obsah souboru (např. konfigurační soubor)
	 * @return bool True pokud je obsah chráněný, jinak false
	 */
    public static function isProtected(string $content): bool
    {
        return str_starts_with(ltrim($content), self::PHP_OPEN);
    }

    /**
     * Precte soubor a vrati cisty JSON obsah (automaticky odstrani guard).
     *
     * @param string $path Cesta k souboru (.json nebo .json.php)
     * @return string Cisty JSON obsah
     * @throws \RuntimeException Pokud soubor nelze precist
     */
    public static function readFile(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }
        return self::strip($content);
    }

    /**
     * Zapise JSON obsah do souboru s PHP exit guardem.
     * Vytvori adresar pokud neexistuje.
     *
     * @param string $path Cilova cesta (mela by mit priponu .json.php)
     * @param string $jsonContent Cisty JSON obsah
     */
    public static function writeProtected(string $path, string $jsonContent): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, self::protect($jsonContent));
    }

    /**
     * Konvertuje existujici .json soubor na chraneny .json.php.
     *
     * @param string $sourcePath Cesta ke zdrojovemu .json souboru
     * @param string $targetPath Cesta k cilovemu .json.php souboru
     * @param bool $deleteSource Zda smazat zdrojovy .json po konverzi
     * @return bool True pokud byla konverze provedena
     * @throws \RuntimeException Pokud zdrojovy soubor nelze precist
     */
    public static function convertToProtected(string $sourcePath, string $targetPath, bool $deleteSource = true): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $sourceContent = file_get_contents($sourcePath);
        if ($sourceContent === false) {
            throw new \RuntimeException("Cannot read file for conversion: {$sourcePath}");
        }

        $json = self::strip($sourceContent);
        self::writeProtected($targetPath, $json);

        $shouldDelete = $deleteSource && ($sourcePath !== $targetPath);
        if ($shouldDelete) {
            unlink($sourcePath);
        }

        return true;
    }

	/**
	 * Vrátí obsah ochranného souboru pro adresář (např. index.php).
	 *
	 * Generuje PHP kód, který zabrání přímému přístupu do adresáře přes webový prohlížeč.
	 * Při pokusu o přístup vrátí HTTP 403 (Forbidden), nastaví textový výstup
	 * a ukončí běh skriptu.
	 *
	 * Tento soubor je typicky generován automaticky (např. instalátorem)
	 * a slouží k ochraně konfiguračních nebo interních souborů.
	 *
	 * @return string PHP kód ochranného souboru
	 */
    public static function directoryGuardContent(): string
    {
        return self::PHP_OPEN . "\n"
            . "/**\n"
            . " * PlatformBridge - Ochrana adresare pred primym pristupem.\n"
            . " * Tento soubor je automaticky vygenerovan instalatorem.\n"
            . " * NEODSTRANUJTE - chrani konfiguracni soubory pred verejnym pristupem.\n"
            . " */\n"
            . "http_response_code(403);\n"
            . "header('Content-Type: text/plain; charset=utf-8');\n"
            . "echo 'Forbidden';\n"
            . "exit;\n";
    }

    /**
     * Vytvori ochranny index.php v danem adresari.
     * Preskoci pokud soubor index.php jiz existuje (neznici uzivatelsky index).
     *
     * @param string $directory Absolutni cesta k adresari
     * @return bool True pokud byl soubor vytvoren, false pokud existoval
     */
    public static function createDirectoryGuard(string $directory): bool
    {
        $dir = rtrim($directory, '/\\');
        $indexPath = $dir . DIRECTORY_SEPARATOR . 'index.php';

        if (file_exists($indexPath)) {
            return false;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($indexPath, self::directoryGuardContent());
        return true;
    }

	/**
	 * Vrátí název souboru s ochrannou PHP příponou.
	 *
	 * Pokud již název obsahuje definovanou ochrannou příponu (např. .php),
	 * vrátí jej beze změny. V opačném případě příponu automaticky přidá.
	 *
	 * Slouží k zajištění, že konfigurační soubory budou vždy chráněny
	 * proti přímému přístupu přes webový server.
	 *
	 * @param string $jsonFilename Název souboru (např. config.json)
	 * @return string Název souboru s ochrannou příponou (např. config.json.php)
	 */
    public static function protectedFilename(string $jsonFilename): string
    {
        if (str_ends_with($jsonFilename, self::PROTECTED_EXTENSION)) {
            return $jsonFilename;
        }
        return $jsonFilename . '.php';
    }
}
