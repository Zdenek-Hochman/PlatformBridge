<?php

namespace Zoom\PlatformBridge\Template;

/**
 * TemplateEngine — vlastní šablonovací engine s kompilací do PHP a cache.
 *
 * Umožňuje bezpečné a rychlé vykreslování šablon s vlastní Smarty-like syntaxí.
 *
 * Typické použití:
 *
 * ```php
 * $engine = new Engine([
 *     'tpl_dir'   => '/path/to/views',
 *     'cache_dir' => '/path/to/cache',
 *     'debug'     => false,
 * ]);
 *
 * $html = $engine
 *     ->assign(['title' => 'Hello', 'items' => [1,2,3]])
 *     ->render('/Atoms/Wrapper');
 *
 * $engine->clear(); // Vyčistí proměnné pro další render
 * ```
 *
 * Bezpečnost:
 *  - Kompilace šablon do PHP s file lockingem
 *  - Blacklist nebezpečných funkcí v parseru (viz třída Parser)
 *  - Automatické čištění expirovaných cache souborů
 *
 * Best practice:
 *  - V produkci nastavte 'debug' => false a zajistěte zápis do cache adresáře
 *  - Každý render volat s novým assign(), případně clear() mezi rendery
 *
 * @see Parser
 * @see https://github.com/virtualzoom/ZoomPlatformBridge Dokumentace projektu
 */
final class Engine
{
    private array $var = [];

    private static array $conf = [
        'checksum' => [],
        'charset' => 'UTF-8',
        'tpl_dir' => "",
        'ext' => "tpl",
        'cache_dir' => "",
        'base_url' => '',
        'remove_comments' => false,
        'debug' => false,
    ];

    private static array $metadata = [
        "tpl_name" => "",
        "base_dir" => "",
        "tpl_path" => "",
        "cache_path" => "",
    ];

    public function __construct($config = [])
    {
        static::configure($config);
        $this->cleanExpiredCacheFiles();
    }

    /**
     * Vytvoří cestu k souboru na základě zadaného adresáře, názvu šablony a přípony.
     *
     * @param string $baseDir Adresář, ve kterém se nachází šablony.
     * @param string $templateName Název šablony.
     * @param string $extension Přípona souboru.
     *
     * @return string Vytvořená cesta k souboru.
     */
    private function createFilePath(string $baseDir, string $templateName, string $extension): string
    {
        return static::reducePath($baseDir . $templateName . '.' . $extension);
    }

    /**
     * Inicializuje metadata šablony.
     *
     * Tato metoda nastaví název šablony a základní adresář do statického pole $metadata.
     * Také vytvoří cestu k šabloně a cache podle zadané šablony a konfigurace.
     * Pokud tpl soubor existuje v některém ze specifikovaných adresářů pro šablony, vrátí true.
     *
     * @param string $template Cesta k souboru šablony
     * @return bool True, pokud byla šablona inicializována úspěšně, jinak vyhodí výjimku
     * @throws \Exception Pokud šablona nebyla nalezena
     */
    private function initializeTemplateMetadata(string $template): bool
    {
        // Nastaví název šablony na název souboru šablony
        static::$metadata['tpl_name'] = basename($template);
        // Nastaví základní adresář na adresář souboru šablony
        static::$metadata['base_dir'] = strpos($template, '/') !== false ? dirname($template) . '/' : '/';

        // Získá seznam adresářů se šablonami
        $directories = (array)static::$conf['tpl_dir'];

        foreach ($directories as $dir) {
            static::$metadata['tpl_path'] = $this->createFilePath($dir . static::$metadata['base_dir'], static::$metadata['tpl_name'], static::$conf['ext']);
            static::$metadata['cache_path'] = $this->createFilePath(static::$conf['cache_dir'] . "/", static::$metadata['tpl_name'] . "." . md5($dir . static::$metadata['base_dir'] . serialize(static::$conf['checksum'])), 'cache.php');

            if (file_exists(static::$metadata['tpl_path'])) {
                return true;
            }
        }

        throw new \Exception('Šablona ' . static::$metadata['tpl_name'] . ' nebyla nalezena!');
    }

    /**
     * Metoda pro získání a aktualizaci cache šablony.
     *
     * @param string $template Název šablony.
     * @return string|bool Cesta k uložené cache šablony nebo false, pokud se nepodařilo inicializovat metadata šablony.
     */
    private function getAndUpdateTemplateCache(string $template): string|bool
    {
        // Zkontroluje, zda inicializace metadata šablony proběhla úspěšně.
        if (!$this->initializeTemplateMetadata($template)) {
            return false;
        }

        // Uloží cestu k souboru cache a k šabloně z metadata.
        $cachePath = static::$metadata['cache_path'];
        $templatePath = static::$metadata['tpl_path'];

        // Zkontroluje, zda soubor cache neexistuje nebo zda byl soubor šablony změněn.

        //TODO: Uncomment this
        // if (!file_exists($cachePath) || (filemtime($cachePath) < filemtime($templatePath))) {
        $this->cacheCompiledTemplate();
        // }

        return $cachePath;
    }

    /**
     * Metoda pro ukládání zkompilované šablony do mezipaměti.
     *
     * Tato metoda otevře soubor s šablonou, přečte její obsah a převede ho na PHP kód.
     * Poté vytvoří adresář pro mezipaměť, pokud ještě neexistuje, a zkontroluje, zda je zapisovatelný.
     * Nakonec zapíše zkompilovaný kód do mezipamětního souboru.
     *
     * @throws \Exception Pokud adresář pro mezipaměť nemá oprávnění k zápisu.
     */
    private function cacheCompiledTemplate(): void
    {
        // Otevře soubor šablony pro čtení.
        $file = fopen(static::$metadata['tpl_path'], "r");

        // Pokud se podaří získat sdílený zámek na souboru.
        if (flock($file, LOCK_SH)) {
            // Přečte obsah šablony do proměnné $code..
            $code = fread($file, filesize(static::$metadata['tpl_path']));

            // Převede načtený kód na PHP pomocí TemplateParser.
            $parsedCode = (new Parser(static::$conf))->parseCodeToPhp($code);

            // Zkontroluje, zda adresář pro mezipaměť existuje, pokud ne, vytvoří ho.
            if (!is_dir(static::$conf['cache_dir'])) {
                mkdir(static::$conf['cache_dir'], 0755, true);
            }

            // Zkontroluje, zda je adresář pro mezipaměť zapisovatelný, pokud ne, vyhodí výjimku.
            if (!is_writable(static::$conf['cache_dir'])) {
                throw new \Exception('Adresář pro mezipaměť ' . static::$conf['cache_dir'] . ' nemá oprávnění k zápisu.');
            }

            // Zapíše zkompilovaný PHP kód do souboru cache.
            file_put_contents(static::$metadata['cache_path'], $parsedCode);

            // Uvolní zámek na soubor.
            flock($file, LOCK_UN);
        }

        fclose($file);
    }

    /**
     * Přiřadí hodnoty k proměnným nebo přidá nové proměnné do kontextu šablony.
     *
     * @param array|string $variable Klíč nebo asociativní pole proměnných, které mají být přiřazeny.
     * @param mixed $value Hodnota, která má být přiřazena, pokud je $variable typu string.
     * @return self Pro řetězení metod (fluent API)
     *
     * @example
     *   $engine->assign('title', 'Nadpis');
     *   $engine->assign(['foo' => 'bar', 'arr' => [1,2,3]]);
     */
    public function assign(array|string $variable, mixed $value = null): self
    {
        if (is_array($variable)) {
            $this->var = $variable + $this->var;
        } else {
            $this->var[$variable] = $value;
        }
        return $this;
    }

    /**
     * Vyčistí všechny proměnné v kontextu šablony.
     *
     * Doporučeno volat před každým novým renderem, pokud engine používáte opakovaně.
     *
     * @return self Pro řetězení metod (fluent API)
     */
    public function clear(): self
    {
        $this->var = [];
        return $this;
    }

    /**
     * Metoda pro čištění expirovaných cache souborů.
     *
     * Tato statická metoda prohledá cache adresář a odstraní všechny soubory cache,
     * které jsou starší než zadaná doba vypršení. Pokud cache adresář neexistuje, automaticky ho vytvoří.
     *
     * @param int $expireTime Časový limit pro expiraci cache souborů (v sekundách). Výchozí hodnota je 3000.
     * @return void
     * @internal Tuto metodu není potřeba volat ručně, je automaticky volána při konstrukci Engine a lze ji použít pro manuální údržbu cache.
     */
    public static function cleanExpiredCacheFiles(int $expireTime = 3000): void
    {
        $cacheDir = static::$conf['cache_dir'];

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
            return; // Nově vytvořená složka je prázdná, není co čistit
        }

        // Získá seznam všech souborů cache v cache adresáři s příponou ".cache.php".
        $files = glob($cacheDir . "*.cache.php");

        // Vypočítá čas vypršení na základě aktuálního času minus doba vypršení.
        $expirationTime = time() - $expireTime;

        foreach ($files as $file) {
            // Zkontroluje, zda je $file soubor a zda jeho čas poslední modifikace je menší než čas vypršení.
            if (is_file($file) && $expirationTime > filemtime($file)) {
                // Odstraní vypršelý soubor cache.
                unlink($file);
            }
        }
    }

    /**
     * Vykreslí šablonu a vrátí výstup jako řetězec.
     *
     * @param string $templateName Název šablony (relativně k tpl_dir, např. '/Atoms/Wrapper')
     * @return string Vykreslená šablona jako HTML
     * @throws \RuntimeException Pokud soubor s cache neexistuje nebo je šablona neplatná
     *
     * @example
     *   $html = $engine->assign(['foo' => 'bar'])->render('/Atoms/Wrapper');
     */
    public function render(string $templateName): string
    {
        // Aktualizuje mezipaměť šablony a získá cestu k souboru mezipaměti.
        $this->getAndUpdateTemplateCache($templateName);
        $cacheFilePath = static::$metadata['cache_path'];

        if (!file_exists($cacheFilePath)) {
            throw new \RuntimeException("Cache file {$cacheFilePath} does not exist.");
        }

        // Najde všechny proměnné v souboru mezipaměti.
        preg_match_all('/\$(\w+)/', file_get_contents($cacheFilePath), $matches);
        $variables = array_unique($matches[1]);

        // Zajistí, že všechny proměnné jsou definovány s výchozí hodnotou "".
        $defaultVariables = array_fill_keys($variables, "");

        // Extrahuje proměnné z pole $this->var do lokálního scope.
        $vars = array_merge($defaultVariables, get_defined_vars());
        unset($vars['this']);

        extract($this->var, EXTR_SKIP);
        extract($vars, EXTR_SKIP);

        //TODO - Přidat kontrolu jestli proměnná není v jiné proměnné
        // foreach ($variables as $variable) {
        // 	if (!array_key_exists($variable, $this->var)) {
        // 		//TODO: Doladit error hlášku pokud není vyplněná proměnná
        // 		throw new \RuntimeException("Variable \${$variable} is not defined in the provided data. - ({$templateName} template)");
        // 	}
        // }

        try {
            // Spustí bufferování výstupu.
            ob_start();

            // Načte (require) soubor mezipaměti, který obsahuje zkompilovaný kód šablony.
            require $cacheFilePath;

            // Získá obsah bufferu a ukončí bufferování.
            $output = ob_get_clean();

            // Vrátí výstup jako text, pokud je režim ladění zapnutý, nebo JSON enkodovaný výstup, pokud není.
            // return static::$conf["debug"] ? $output : json_encode($output, JSON_UNESCAPED_UNICODE);
            return $output;
        } catch (\Throwable $e) {
            // Ukončí bufferování v případě chyby.
            ob_end_clean();
            // Vrátí chybovou zprávu jako JSON enkodovaný text.
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Metoda pro konfiguraci nastavení šablony.
     *
     * @param mixed $setting Název nastavení nebo asociativní pole s nastaveními.
     * @param mixed $value Hodnota nastavení (pouze pokud je $setting název nastavení).
     * @return void
     */
    private static function configure(mixed $setting, mixed $value = null): void
    {
        if (is_array($setting)) {
            foreach ($setting as $key => $value) {
                static::configure($key, $value);
            }
        } elseif (isset(static::$conf[$setting])) {
            static::$conf[$setting] = $value;
            static::$conf['checksum'][$setting] = $value;
        }
    }

    /**
     * Zjednodušuje cestu tím, že odstraňuje nadbytečné slashe a vrací relativní cestu.
     * Odstraňuje nadbytečné slashe, zredukuje sekvence `./` a `../` na jejich odpovídající zjednodušené formy.
     *
     * @param string $path Cesta, kterou chceme redukovat.
     * @return string Redukovaná cesta.
     * @internal Tuto metodu používá Engine pro normalizaci cest k šablonám a cache souborům.
     */
    public static function reducePath(string $path): string
    {
        // Odstraňuje nadbytečné slashe (/), zanechává pouze jeden slash mezi složkami.
        $path = preg_replace("#(/+)#", "/", $path);

        // Odstraňuje nadbytečné sekvence ./, které se nacházejí v cestě.
        $path = preg_replace("#(/\./+)#", "/", $path);

        // Zjednodušuje sekvence ../. Odstraňuje je postupně, dokud nebudou odstraněny všechny.
        while (preg_match('#\w+\.\./#', $path)) {
            // Odstraňuje sekvenci složky následované ../, která se nachází v cestě.
            $path = preg_replace('#\w+/\.\./#', '', $path);
        }

        return $path;
    }
}
