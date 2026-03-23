<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Security;

/**
 * Ochrana JSON konfiguracnich souboru pred primym pristupem z weboveho prohlizece.
 *
 * Problem:
 *   Pokud je koren projektu (nebo jeho cast) pristupny pres web server
 *   (napr. /www slozka), soubory .json jsou citelne pres URL.
 *   Utocnik tak muze ziskat strukturu aplikace, cesty, konfiguraci generatoru apod.
 *
 * Reseni - PHP Exit Guard:
 *   JSON data se ulozi do souboru s priponou .json.php. Soubor obsahuje
 *   PHP hlavicku s header(403) + exit() nasledovanou JSON daty.
 *
 *   Pri pristupu pres prohlizec:
 *     -> web server preda soubor PHP enginu -> exit() -> HTTP 403 -> JSON zustane skryty
 *
 *   Pri cteni aplikaci (file_get_contents):
 *     -> PHP se nespusti -> guard se programove odstrani -> JSON se normalne zparsuje
 *
 * Vyhody:
 *   - Funguje na JAKEMKOLI PHP hostingu (Apache, Nginx, IIS, LiteSpeed)
 *   - Nevyzaduje zmeny konfigurace serveru (.htaccess, nginx.conf, ...)
 *   - JSON data zustavaji cista - guard je jen obalujici ochranna vrstva
 *   - Zpetna kompatibilita - aplikace umi precist i nechranene .json soubory
 *   - Soubor .json.php nelze zneuzit k injekci kodu - obsah se cte pres
 *     file_get_contents + json_decode, nikdy pres require/include
 *
 * Adresarova ochrana:
 *   Vedle ochrannych .json.php souboru trida generuje i index.php strazce,
 *   kteri brani directory listing v adresarich s konfiguraci.
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
     * Vrati PHP exit guard hlavicku s komentarem pro uzivatele.
     * Pouziva se pri generovani chranenych souboru.
     *
     * Poznamka: Implementovano jako metoda (ne konstanta/heredoc), protoze
     * PHP tokenizer v nekterych verzich nespravne parsuje PHP oteviraci
     * znacku uvnitr heredoc/nowdoc v class konstantach.
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

    // --- Ochrana obsahu -----------------------------------------------

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

        // Hledame POSLEDNI uzaviraci PHP znacku - tj. skutecne uzavreni PHP bloku.
        $needle = self::PHP_CLOSE;
        $pos = strrpos($content, $needle);
        if ($pos === false) {
            return $content;
        }

        return ltrim(substr($content, $pos + strlen($needle)));
    }

    /**
     * Zjisti, zda obsah souboru obsahuje PHP exit guard.
     */
    public static function isProtected(string $content): bool
    {
        return str_starts_with(ltrim($content), self::PHP_OPEN);
    }

    // --- Souborove operace --------------------------------------------

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
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
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
    public static function convertToProtected(
        string $sourcePath,
        string $targetPath,
        bool $deleteSource = true
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file for conversion: {$sourcePath}");
        }

        // Pokud je obsah jiz chraneny, extrahuj cisty JSON
        $jsonContent = self::strip($content);

        self::writeProtected($targetPath, $jsonContent);

        if ($deleteSource && $sourcePath !== $targetPath) {
            unlink($sourcePath);
        }

        return true;
    }

    // --- Adresarova ochrana -------------------------------------------

    /**
     * Vygeneruje obsah ochranneho index.php souboru pro adresar.
     *
     * Tento soubor brani vypisu obsahu adresare (directory listing)
     * a vraci HTTP 403 pri primem pristupu na URL adresare.
     * Slouzi jako doplnkova ochrana vedle .json.php souboru.
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
        $directory = rtrim($directory, '/\\');
        $indexFile = $directory . DIRECTORY_SEPARATOR . 'index.php';

        if (file_exists($indexFile)) {
            return false;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($indexFile, self::directoryGuardContent());
        return true;
    }

    // --- Pomocne metody -----------------------------------------------

    /**
     * Vrati chraneny nazev souboru pro dany .json soubor.
     * Napr. "blocks.json" -> "blocks.json.php"
     */
    public static function protectedFilename(string $jsonFilename): string
    {
        if (str_ends_with($jsonFilename, self::PROTECTED_EXTENSION)) {
            return $jsonFilename;
        }
        return $jsonFilename . '.php';
    }

    /**
     * Vrati nechraneny nazev souboru (odstrani .php priponu).
     * Napr. "blocks.json.php" -> "blocks.json"
     */
    public static function plainFilename(string $protectedFilename): string
    {
        if (str_ends_with($protectedFilename, self::PROTECTED_EXTENSION)) {
            return substr($protectedFilename, 0, -4); // Remove .php
        }
        return $protectedFilename;
    }
}
